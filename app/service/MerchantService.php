<?php

declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class MerchantService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly InternalGameApiClient $runtime,
        private readonly IpWhitelistService $ipWhitelist,
    ) {
    }

    public function create(array $input, array $actor, string $ip, string $agent): array
    {
        if ((string) ($actor['role_code'] ?? '') !== 'platform_admin' || (int) ($actor['channel_id'] ?? -1) !== 0) {
            throw new RuntimeException('PLATFORM_ADMIN_REQUIRED');
        }

        $agencyUid = trim((string) ($input['agencyUid'] ?? ''));
        $showName = trim((string) ($input['showName'] ?? ''));
        $backRateText = trim((string) ($input['backRate'] ?? '0'));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $backRateText)) throw new InvalidArgumentException('返点必须为最多两位小数的数字');
        $backRate = round((float) $backRateText, 2);
        $phone = trim((string) ($input['phone'] ?? ''));
        $socialCode = trim((string) ($input['socialCode'] ?? ''));
        $creditLimit = $this->limitInteger($input['creditLimit'] ?? 0, '信用额度');
        $purchaseLimit = $this->limitInteger($input['purchaseLimit'] ?? 0, '采购额度');
        $status = (int) ($input['status'] ?? 1) === 1 ? 1 : 0;
        $adminIpRule = trim((string) ($input['adminIpRule'] ?? ''));
        $adminPassword = (string) ($input['adminPassword'] ?? '');
        $adminPasswordConfirmation = (string) ($input['adminPasswordConfirmation'] ?? '');
        $codes = $input['currencyCodes'] ?? [];
        if (is_string($codes)) $codes = array_filter(array_map('trim', explode(',', $codes)));
        $codes = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            is_array($codes) ? $codes : []
        )));
        $defaultCurrency = strtoupper(trim((string) ($input['defaultCurrency'] ?? '')));

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,31}$/', $agencyUid)) {
            throw new InvalidArgumentException('商户编码必须为 3 到 32 位字母、数字、下划线或短横线');
        }
        if (mb_strlen($showName) < 2 || mb_strlen($showName) > 32) {
            throw new InvalidArgumentException('商户名称必须为 2 到 32 个字符');
        }
        if ($backRate < 0 || $backRate > 100) throw new InvalidArgumentException('返点必须在 0 到 100 之间');
        if (mb_strlen($phone) > 20 || mb_strlen($socialCode) > 20) throw new InvalidArgumentException('联系方式不能超过 20 个字符');
        if (!$this->ipWhitelist->isValidRule($adminIpRule) || in_array(strtolower($adminIpRule), ['0.0.0.0/0', '::/0'], true)) {
            throw new InvalidArgumentException('后台登录 IP 必须是有效的 IP 地址或 CIDR');
        }
        if ($adminPassword !== $adminPasswordConfirmation) {
            throw new InvalidArgumentException('两次输入的后台密码不一致');
        }
        if (!$this->isStrongPassword($adminPassword)) {
            throw new InvalidArgumentException('后台密码至少 12 位，并且必须包含大小写字母、数字和特殊符号');
        }
        if ($codes === [] || !in_array($defaultCurrency, $codes, true)) {
            throw new InvalidArgumentException('至少启用一个币种，且默认币种必须在启用列表中');
        }

        $db = Db::connect('game');
        if ($db->table('d_channel_parter')->where('agency_uid', $agencyUid)->find()) {
            throw new RuntimeException('MERCHANT_CODE_CONFLICT');
        }
        $validCodes = array_map('strtoupper', array_map('strval', $db->table('s_currency_profile')
            ->where('status', 1)->whereIn('currency_code', $codes)->column('currency_code')));
        if (array_diff($codes, $validCodes) !== []) throw new InvalidArgumentException('包含未启用或不存在的币种');

        // Compatible with api_service Game::applykey20261103.
        $apiKey = base64_encode(random_bytes(32));
        $now = time();
        try {
            $channelId = (int) $db->transaction(function ($db) use ($agencyUid, $showName, $apiKey, $backRate, $phone, $socialCode, $defaultCurrency, $creditLimit, $purchaseLimit, $status, $codes, $now): int {
                $channelId = (int) $db->table('d_channel_parter')->insertGetId([
                    'agency_uid' => $agencyUid,
                    'apikey' => $apiKey,
                    'show_name' => $showName,
                    'back_rate' => $backRate,
                    'phone' => $phone,
                    'social_code' => $socialCode,
                    'currency_code' => $defaultCurrency,
                    'credit_limit' => $creditLimit,
                    'purchase_limit' => $purchaseLimit,
                    'used_coin' => 0,
                    'status' => $status,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                foreach ($codes as $code) {
                    $db->table('s_channel_currency')->insert([
                        'channel_id' => $channelId,
                        'currency_code' => $code,
                        'is_default' => $code === $defaultCurrency ? 1 : 0,
                        'status' => 1,
                    ]);
                }
                return $channelId;
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate entry')) throw new RuntimeException('MERCHANT_CODE_CONFLICT', 0, $exception);
            throw $exception;
        }

        try {
            $adminAccount = Db::connect('partner')->transaction(function ($partner) use ($agencyUid, $showName, $channelId, $adminPassword, $adminIpRule, $status): array {
                $adminUsername = $this->availableAdminUsername($partner, $agencyUid, $channelId);
                $dateTime = (new DateTimeImmutable())->format('Y-m-d H:i:s');
                $adminUserId = (int) $partner->table('partner_admin_user')->insertGetId([
                    'channel_id' => $channelId,
                    'username' => $adminUsername,
                    'password_hash' => password_hash($adminPassword, PASSWORD_ARGON2ID),
                    'display_name' => mb_substr($showName . '管理员', 0, 64),
                    'role_code' => 'merchant_admin',
                    'status' => $status,
                    'failed_login_count' => 0,
                    'locked_until' => null,
                    'last_login_at' => null,
                    'last_login_ip' => '',
                    'password_changed_at' => $dateTime,
                    'created_at' => $dateTime,
                    'updated_at' => $dateTime,
                ]);
                $partner->table('partner_admin_ip_whitelist')->insert([
                    'channel_id' => $channelId,
                    'ip_or_cidr' => $adminIpRule,
                    'status' => 1,
                    'remark' => '创建商户时生成的初始登录白名单',
                    'created_by' => $adminUserId,
                    'created_at' => $dateTime,
                    'updated_at' => $dateTime,
                ]);

                return ['adminUserId' => $adminUserId, 'adminUsername' => $adminUsername];
            });
        } catch (\Throwable $exception) {
            try {
                $db->transaction(function ($game) use ($channelId): void {
                    $game->table('s_channel_currency')->where('channel_id', $channelId)->delete();
                    $game->table('d_channel_parter')->where('id', $channelId)->delete();
                });
            } catch (\Throwable) {
                throw new RuntimeException('MERCHANT_ADMIN_CREATE_ROLLBACK_FAILED', 0, $exception);
            }
            throw new RuntimeException('MERCHANT_ADMIN_CREATE_FAILED', 0, $exception);
        }

        $runtimeStatus = 'not_configured';
        $runtimeError = '';
        if ($this->runtime->isConfigured()) {
            try {
                $loaded = $this->runtime->reloadCurrency();
                $runtimeStatus = (!empty($loaded['success']) || ($loaded['message'] ?? '') === 'succ') ? 'loaded' : 'failed';
                if ($runtimeStatus === 'failed') $runtimeError = '运行端未确认新商户币种配置';
            } catch (\Throwable $exception) {
                $runtimeStatus = 'failed';
                $runtimeError = $exception->getMessage();
            }
        }

        $safeAfter = [
            'channelId' => $channelId,
            'agencyUid' => $agencyUid,
            'showName' => $showName,
            'backRate' => $backRate,
            'currencyCodes' => $codes,
            'defaultCurrency' => $defaultCurrency,
            'creditLimit' => $creditLimit,
            'purchaseLimit' => $purchaseLimit,
            'status' => $status,
            'apiKeySuffix' => substr($apiKey, -4),
            'adminUserId' => $adminAccount['adminUserId'],
            'adminUsername' => $adminAccount['adminUsername'],
            'adminIpRule' => $adminIpRule,
            'runtimeStatus' => $runtimeStatus,
        ];
        $this->audit->record('merchant.create', $runtimeStatus === 'failed' ? 'failed' : 'success', $ip, $agent,
            (int) $actor['id'], 0, 'platform_admin', 'merchant', (string) $channelId, $runtimeError, null, $safeAfter);

        return $safeAfter + ['apiKey' => $apiKey, 'adminPassword' => $adminPassword, 'runtimeError' => $runtimeError];
    }

    public function update(int $channelId, array $input, array $actor, string $ip, string $agent): array
    {
        if ((string) ($actor['role_code'] ?? '') !== 'platform_admin' || (int) ($actor['channel_id'] ?? -1) !== 0) {
            throw new RuntimeException('PLATFORM_ADMIN_REQUIRED');
        }

        $showName = trim((string) ($input['showName'] ?? ''));
        $backRateText = trim((string) ($input['backRate'] ?? '0'));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $backRateText)) throw new InvalidArgumentException('返点必须为最多两位小数的数字');
        $backRate = round((float) $backRateText, 2);
        $phone = trim((string) ($input['phone'] ?? ''));
        $socialCode = trim((string) ($input['socialCode'] ?? ''));
        $creditLimit = $this->limitInteger($input['creditLimit'] ?? 0, '信用额度');
        $purchaseLimit = $this->limitInteger($input['purchaseLimit'] ?? 0, '采购额度');
        $status = (int) ($input['status'] ?? 0) === 1 ? 1 : 0;

        if (mb_strlen($showName) < 2 || mb_strlen($showName) > 32) throw new InvalidArgumentException('商户名称必须为 2 到 32 个字符');
        if ($backRate < 0 || $backRate > 100) throw new InvalidArgumentException('返点必须在 0 到 100 之间');
        if (mb_strlen($phone) > 20 || mb_strlen($socialCode) > 20) throw new InvalidArgumentException('联系方式不能超过 20 个字符');

        $game = Db::connect('game');
        $before = $game->table('d_channel_parter')->where('id', $channelId)->find();
        if (!is_array($before)) throw new RuntimeException('MERCHANT_NOT_FOUND');
        $after = [
            'show_name' => $showName,
            'back_rate' => $backRate,
            'phone' => $phone,
            'social_code' => $socialCode,
            'credit_limit' => $creditLimit,
            'purchase_limit' => $purchaseLimit,
            'status' => $status,
            'update_time' => time(),
        ];

        $game->transaction(function ($db) use ($channelId, $after): void {
            $db->table('d_channel_parter')->where('id', $channelId)->update($after);
        });
        try {
            Db::connect('partner')->table('partner_admin_user')->where('channel_id', $channelId)->update([
                'status' => $status,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $game->table('d_channel_parter')->where('id', $channelId)->update([
                'show_name' => $before['show_name'],
                'back_rate' => $before['back_rate'],
                'phone' => $before['phone'],
                'social_code' => $before['social_code'],
                'credit_limit' => $before['credit_limit'],
                'purchase_limit' => $before['purchase_limit'],
                'status' => $before['status'],
                'update_time' => $before['update_time'],
            ]);
            throw new RuntimeException('MERCHANT_ADMIN_SYNC_FAILED', 0, $exception);
        }

        $runtimeStatus = 'not_configured';
        $runtimeError = '';
        if ($this->runtime->isConfigured()) {
            try {
                $loaded = $this->runtime->reloadMerchant($channelId);
                $runtimeStatus = (!empty($loaded['success']) || ($loaded['message'] ?? '') === 'succ') ? 'loaded' : 'failed';
                if ($runtimeStatus === 'failed') $runtimeError = '运行端未确认商户配置加载';
            } catch (\Throwable $exception) {
                $runtimeStatus = 'failed';
                $runtimeError = $exception->getMessage();
            }
        }

        $response = [
            'channelId' => $channelId,
            'showName' => $showName,
            'backRate' => $backRate,
            'phone' => $phone,
            'socialCode' => $socialCode,
            'creditLimit' => $creditLimit,
            'purchaseLimit' => $purchaseLimit,
            'status' => $status,
            'runtimeStatus' => $runtimeStatus,
            'runtimeError' => $runtimeError,
        ];
        $safeBefore = array_intersect_key($before, array_flip(['show_name', 'back_rate', 'phone', 'social_code', 'credit_limit', 'purchase_limit', 'status']));
        $this->audit->record('merchant.update', $runtimeStatus === 'failed' ? 'failed' : 'success', $ip, $agent,
            (int) $actor['id'], 0, 'platform_admin', 'merchant', (string) $channelId, $runtimeError, $safeBefore, $response);

        return $response;
    }

    private function availableAdminUsername(mixed $db, string $agencyUid, int $channelId): string
    {
        foreach ([$agencyUid, $agencyUid . '_admin', 'merchant_' . $channelId] as $candidate) {
            if (!$db->table('partner_admin_user')->where('username', $candidate)->find()) return $candidate;
        }
        throw new RuntimeException('MERCHANT_ADMIN_USERNAME_CONFLICT');
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    private function limitInteger(mixed $value, string $label): int
    {
        $text = trim((string) $value);
        if (!preg_match('/^(?:-1|\d+)$/', $text)) throw new InvalidArgumentException($label . '必须为 -1 或非负整数');
        $number = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]]);
        if ($number === false) throw new InvalidArgumentException($label . '超出允许范围');
        return (int) $number;
    }
}
