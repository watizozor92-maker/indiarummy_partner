<?php

declare(strict_types=1);

namespace app\command;

use app\service\IpWhitelistService;
use DateTimeImmutable;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

final class CreateAdmin extends Command
{
    private const ROLES = ['platform_admin', 'merchant_admin', 'merchant_viewer'];

    public function __construct(private readonly IpWhitelistService $ipWhitelist)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('partner:admin:create')
            ->setDescription('Create an administrator and initial IP whitelist rule')
            ->addArgument('username', Argument::REQUIRED, 'Unique login username')
            ->addOption('display-name', null, Option::VALUE_REQUIRED, 'Display name', '')
            ->addOption('role', null, Option::VALUE_REQUIRED, 'platform_admin/merchant_admin/merchant_viewer', 'platform_admin')
            ->addOption('channel', null, Option::VALUE_REQUIRED, '0 for platform, otherwise d_channel_parter.id', '0')
            ->addOption('ip', null, Option::VALUE_REQUIRED, 'Allowed login IP or CIDR');
    }

    protected function execute(Input $input, Output $output): int
    {
        $username = trim((string) $input->getArgument('username'));
        $displayName = trim((string) $input->getOption('display-name'));
        $roleCode = trim((string) $input->getOption('role'));
        $channelId = (int) $input->getOption('channel');
        $ipRule = trim((string) $input->getOption('ip'));

        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            $output->writeln('<error>Username must be 3-64 characters: letters, numbers, dot, underscore or dash.</error>');
            return 1;
        }
        if (!in_array($roleCode, self::ROLES, true)) {
            $output->writeln('<error>Invalid role.</error>');
            return 1;
        }
        if (($roleCode === 'platform_admin' && $channelId !== 0)
            || ($roleCode !== 'platform_admin' && $channelId <= 0)) {
            $output->writeln('<error>Platform administrators require channel 0; merchant roles require a positive channel.</error>');
            return 1;
        }
        if (!$this->ipWhitelist->isValidRule($ipRule)) {
            $output->writeln('<error>--ip must be a valid IPv4/IPv6 address or CIDR.</error>');
            return 1;
        }
        if ($channelId > 0 && !Db::connect('game')->table('d_channel_parter')->where('id', $channelId)->find()) {
            $output->writeln('<error>The merchant channel does not exist.</error>');
            return 1;
        }

        $password = (string) $output->askHidden($input, 'Password (minimum 12 characters): ');
        $confirmation = (string) $output->askHidden($input, 'Confirm password: ');
        if ($password !== $confirmation) {
            $output->writeln('<error>Passwords do not match.</error>');
            return 1;
        }
        if (!$this->isStrongPassword($password)) {
            $output->writeln('<error>Password must be at least 12 characters and contain upper, lower, number and symbol.</error>');
            return 1;
        }

        $db = Db::connect('partner');
        if ($db->table('partner_admin_user')->where('username', $username)->find()) {
            $output->writeln('<error>Username already exists.</error>');
            return 1;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $db->transaction(function () use ($db, $username, $displayName, $roleCode, $channelId, $ipRule, $password, $now): void {
            $userId = (int) $db->table('partner_admin_user')->insertGetId([
                'channel_id' => $channelId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                'display_name' => $displayName !== '' ? $displayName : $username,
                'role_code' => $roleCode,
                'status' => 1,
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => null,
                'last_login_ip' => '',
                'password_changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->table('partner_admin_ip_whitelist')->insert([
                'channel_id' => $channelId,
                'ip_or_cidr' => $ipRule,
                'status' => 1,
                'remark' => 'Initial rule created with administrator',
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $output->writeln(sprintf('<info>Administrator %s created for channel %d.</info>', $username, $channelId));
        return 0;
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}
