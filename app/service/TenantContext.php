<?php

declare(strict_types=1);

namespace app\service;

use RuntimeException;
use think\facade\Session;

final class TenantContext
{
    public function user(): array
    {
        $user = Session::get('partner_auth');
        if (!is_array($user) || empty($user['id'])) {
            throw new RuntimeException('Authentication is required.');
        }

        return $user;
    }

    public function channelId(): int
    {
        return (int) ($this->user()['channel_id'] ?? 0);
    }

    public function roleCode(): string
    {
        return (string) ($this->user()['role_code'] ?? '');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->channelId() === 0 && $this->roleCode() === 'platform_admin';
    }

    /**
     * Resolve the tenant scope without ever trusting a merchant-supplied channel.
     * A null result means a platform-wide query.
     */
    public function resolveChannelId(?int $requestedChannelId = null): ?int
    {
        if (!$this->isPlatformAdmin()) {
            return $this->channelId();
        }

        return $requestedChannelId !== null && $requestedChannelId >= 0
            ? $requestedChannelId
            : null;
    }
}
