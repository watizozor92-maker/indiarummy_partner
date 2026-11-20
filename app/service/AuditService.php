<?php

declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use think\facade\Db;
use Throwable;

final class AuditService
{
    public function record(
        string $action,
        string $result,
        string $ipAddress,
        string $userAgent = '',
        int $actorId = 0,
        int $channelId = 0,
        string $roleCode = '',
        string $resourceType = '',
        string $resourceId = '',
        string $reason = '',
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?string $requestId = null
    ): void {
        try {
            Db::connect('partner')->table('partner_audit_log')->insert([
                'request_id' => $requestId ?: bin2hex(random_bytes(16)),
                'actor_id' => $actorId,
                'channel_id' => $channelId,
                'role_code' => $roleCode,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'result' => $result,
                'reason' => mb_substr($reason, 0, 255),
                'ip_address' => mb_substr($ipAddress, 0, 64),
                'user_agent' => mb_substr($userAgent, 0, 500),
                'before_json' => $this->encode($before),
                'after_json' => $this->encode($after),
                'metadata_json' => $this->encode($metadata),
                'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $exception) {
            trace(sprintf('Audit write failed for %s: %s', $action, $exception->getMessage()), 'error');
        }
    }

    private function encode(?array $value): ?string
    {
        return $value === null
            ? null
            : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
