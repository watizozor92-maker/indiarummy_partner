<?php

declare(strict_types=1);

namespace app\service;

use app\exception\AuthenticationException;
use DateInterval;
use DateTimeImmutable;
use think\facade\Db;
use think\facade\Session;

final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(
        private readonly IpWhitelistService $ipWhitelist,
        private readonly AuditService $audit
    ) {
    }

    public function attempt(string $username, string $password, string $ipAddress, string $userAgent): array
    {
        $username = trim($username);
        $now = new DateTimeImmutable();
        $db = Db::connect('partner');

        $result = $db->transaction(function () use ($db, $username, $password, $ipAddress, $now): array {
            $user = $db->table('partner_admin_user')->where('username', $username)->lock(true)->find();
            if (!is_array($user)) {
                return $this->failure('用户名或密码错误', 'unknown_username');
            }

            $userId = (int) $user['id'];
            $channelId = (int) $user['channel_id'];
            $roleCode = (string) $user['role_code'];

            if ((int) $user['status'] !== 1) {
                return $this->failure(
                    '登录失败，请检查账号状态或联系平台管理员',
                    'account_disabled',
                    $userId,
                    $channelId,
                    $roleCode
                );
            }

            if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > $now->getTimestamp()) {
                return $this->failure(
                    '登录失败，请检查账号状态或联系平台管理员',
                    'account_locked',
                    $userId,
                    $channelId,
                    $roleCode
                );
            }

            if (!$this->ipWhitelist->isAllowed($channelId, $ipAddress)) {
                return $this->failure(
                    '登录失败，请检查账号状态或联系平台管理员',
                    'ip_not_allowed',
                    $userId,
                    $channelId,
                    $roleCode
                );
            }

            if (!password_verify($password, (string) $user['password_hash'])) {
                $failedCount = (int) $user['failed_login_count'];
                if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= $now->getTimestamp()) {
                    $failedCount = 0;
                }
                ++$failedCount;
                $lockedUntil = $failedCount >= self::MAX_FAILED_ATTEMPTS
                    ? $now->add(new DateInterval('PT' . self::LOCK_MINUTES . 'M'))->format('Y-m-d H:i:s')
                    : null;

                $db->table('partner_admin_user')->where('id', $userId)->update([
                    'failed_login_count' => $failedCount,
                    'locked_until' => $lockedUntil,
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);

                return $this->failure(
                    '用户名或密码错误',
                    $lockedUntil === null ? 'invalid_password' : 'too_many_failures',
                    $userId,
                    $channelId,
                    $roleCode
                );
            }

            $passwordHash = (string) $user['password_hash'];
            if (password_needs_rehash($passwordHash, PASSWORD_ARGON2ID)) {
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
            }

            $db->table('partner_admin_user')->where('id', $userId)->update([
                'password_hash' => $passwordHash,
                'failed_login_count' => 0,
                'locked_until' => null,
                'last_login_at' => $now->format('Y-m-d H:i:s'),
                'last_login_ip' => $ipAddress,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'identity' => [
                'id' => $userId,
                'channel_id' => $channelId,
                'username' => (string) $user['username'],
                'display_name' => (string) $user['display_name'],
                'role_code' => $roleCode,
                'login_at' => $now->format(DATE_ATOM),
            ]];
        });

        if (empty($result['ok'])) {
            $this->audit->record(
                'auth.login',
                'denied',
                $ipAddress,
                $userAgent,
                (int) ($result['actor_id'] ?? 0),
                (int) ($result['channel_id'] ?? 0),
                (string) ($result['role_code'] ?? ''),
                empty($result['actor_id']) ? '' : 'admin_user',
                empty($result['actor_id']) ? '' : (string) $result['actor_id'],
                (string) ($result['reason'] ?? 'authentication_failed')
            );
            throw new AuthenticationException((string) $result['message']);
        }

        $identity = $result['identity'];
        Session::regenerate(true);
        Session::set('partner_auth', $identity);

        $this->audit->record(
            'auth.login',
            'success',
            $ipAddress,
            $userAgent,
            (int) $identity['id'],
            (int) $identity['channel_id'],
            (string) $identity['role_code'],
            'admin_user',
            (string) $identity['id']
        );

        return $identity;
    }

    public function logout(string $ipAddress, string $userAgent): void
    {
        $identity = Session::get('partner_auth', []);
        if (is_array($identity) && !empty($identity['id'])) {
            $this->audit->record(
                'auth.logout',
                'success',
                $ipAddress,
                $userAgent,
                (int) $identity['id'],
                (int) ($identity['channel_id'] ?? 0),
                (string) ($identity['role_code'] ?? ''),
                'admin_user',
                (string) $identity['id']
            );
        }

        Session::clear();
        Session::regenerate(true);
    }

    public function changePassword(array $identity,string $currentPassword,string $newPassword,string $ipAddress,string $userAgent):void
    {
        if(strlen($newPassword)<12||preg_match('/[a-z]/',$newPassword)!==1||preg_match('/[A-Z]/',$newPassword)!==1||preg_match('/\d/',$newPassword)!==1||preg_match('/[^A-Za-z0-9]/',$newPassword)!==1)throw new AuthenticationException('新密码至少 12 位，并包含大小写字母、数字和符号');
        if(hash_equals($currentPassword,$newPassword))throw new AuthenticationException('新密码不能与当前密码相同');
        $db=Db::connect('partner');$user=$db->table('partner_admin_user')->where('id',(int)$identity['id'])->find();
        if(!is_array($user)||!password_verify($currentPassword,(string)$user['password_hash'])){$this->audit->record('auth.password.change','denied',$ipAddress,$userAgent,(int)$identity['id'],(int)$identity['channel_id'],(string)$identity['role_code'],'admin_user',(string)$identity['id'],'invalid_current_password');throw new AuthenticationException('当前密码错误');}
        $now=(new DateTimeImmutable())->format('Y-m-d H:i:s');$db->table('partner_admin_user')->where('id',(int)$identity['id'])->update(['password_hash'=>password_hash($newPassword,PASSWORD_ARGON2ID),'password_changed_at'=>$now,'updated_at'=>$now]);
        Session::regenerate(true);Session::set('partner_auth',$identity);$this->audit->record('auth.password.change','success',$ipAddress,$userAgent,(int)$identity['id'],(int)$identity['channel_id'],(string)$identity['role_code'],'admin_user',(string)$identity['id']);
    }

    private function failure(
        string $message,
        string $reason,
        int $actorId = 0,
        int $channelId = 0,
        string $roleCode = ''
    ): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'reason' => $reason,
            'actor_id' => $actorId,
            'channel_id' => $channelId,
            'role_code' => $roleCode,
        ];
    }
}
