<?php

declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class InventoryGrantService
{
    public function __construct(private readonly InventoryAccessPolicy $policy, private readonly AuditService $audit)
    {
    }

    public function get(int $channelId): array
    {
        $row = Db::connect('partner')->table('partner_inventory_grant')->where('channel_id', $channelId)->find();
        if (!is_array($row)) {
            return $this->emptyGrant($channelId);
        }

        return $this->hydrate($row);
    }

    public function effectiveFor(array $actor, int $channelId, int $gameId = 0, string $currencyCode = ''): array
    {
        if ((string) ($actor['role_code'] ?? '') === 'platform_admin' && (int) ($actor['channel_id'] ?? -1) === 0) {
            return [
                'channelId' => $channelId, 'visibility' => 'full', 'canView' => true,
                'canViewLedger' => true, 'adjustMode' => 'direct', 'canAdjust' => true,
                'singleLimit' => 0.0, 'dailyLimit' => 0.0, 'scopeAllowed' => true,
            ];
        }
        if ((int) ($actor['channel_id'] ?? 0) !== $channelId) {
            return $this->denied($channelId);
        }

        $grant = $this->get($channelId);
        $active = (int) $grant['status'] === 1
            && ($grant['expiresAt'] === null || strtotime((string) $grant['expiresAt']) > time());
        $scopeAllowed = $active && ($gameId <= 0 || $currencyCode === '' || $this->policy->scopeAllows($grant, $gameId, $currencyCode));
        $canView = $scopeAllowed && $grant['visibility'] !== 'none';
        $roleCanAdjust = (string) ($actor['role_code'] ?? '') === 'merchant_admin';

        return array_merge($grant, [
            'canView' => $canView,
            'canViewLedger' => $canView && (bool) $grant['canViewLedger'],
            'canAdjust' => $canView && $roleCanAdjust && $grant['adjustMode'] !== 'none',
            'scopeAllowed' => $scopeAllowed,
        ]);
    }

    public function save(int $channelId, array $input, array $actor, string $ip, string $agent): array
    {
        $this->assertPlatform($actor);
        if (!Db::connect('game')->table('d_channel_parter')->where('id', $channelId)->find()) {
            throw new RuntimeException('商户不存在');
        }
        $normalized = $this->policy->normalizeGrant($input);
        $gameDb = Db::connect('game');
        if ($normalized['gameIds'] !== []) {
            $validGames = array_map('intval', $gameDb->table('s_game')->whereIn('id', $normalized['gameIds'])->column('id'));
            if (array_diff($normalized['gameIds'], $validGames) !== []) throw new InvalidArgumentException('游戏范围包含不存在的游戏 ID');
        }
        if ($normalized['currencyCodes'] !== []) {
            $validCurrencies = array_map('strtoupper', array_map('strval', $gameDb->table('s_currency_profile')
                ->where('status', 1)->whereIn('currency_code', $normalized['currencyCodes'])->column('currency_code')));
            if (array_diff($normalized['currencyCodes'], $validCurrencies) !== []) throw new InvalidArgumentException('币种范围包含未启用的币种');
        }
        $expiresAt = trim((string) ($input['expiresAt'] ?? ''));
        if ($expiresAt !== '') {
            $expires = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expiresAt)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAt);
            if (!$expires || $expires->getTimestamp() <= time()) {
                throw new InvalidArgumentException('授权到期时间必须晚于当前时间');
            }
            $expiresAt = $expires->format('Y-m-d H:i:s');
        } else {
            $expiresAt = null;
        }

        $db = Db::connect('partner');
        $before = $db->table('partner_inventory_grant')->where('channel_id', $channelId)->find();
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $values = [
            'visibility' => $normalized['visibility'],
            'can_view_ledger' => $normalized['canViewLedger'] ? 1 : 0,
            'adjust_mode' => $normalized['adjustMode'],
            'game_ids_json' => $normalized['gameIds'] === [] ? null : json_encode($normalized['gameIds'], JSON_THROW_ON_ERROR),
            'currency_codes_json' => $normalized['currencyCodes'] === [] ? null : json_encode($normalized['currencyCodes'], JSON_THROW_ON_ERROR),
            'single_limit' => number_format($normalized['singleLimit'], 4, '.', ''),
            'daily_limit' => number_format($normalized['dailyLimit'], 4, '.', ''),
            'expires_at' => $expiresAt,
            'status' => (int) ($input['status'] ?? 1) === 1 ? 1 : 0,
            'updated_by' => (int) $actor['id'],
            'updated_at' => $now,
        ];
        if (is_array($before)) {
            $db->table('partner_inventory_grant')->where('id', (int) $before['id'])->update($values);
        } else {
            $db->table('partner_inventory_grant')->insert($values + [
                'channel_id' => $channelId,
                'created_by' => (int) $actor['id'],
                'created_at' => $now,
            ]);
        }
        $after = $this->get($channelId);
        $this->audit->record('inventory.grant.update', 'success', $ip, $agent, (int) $actor['id'], 0,
            'platform_admin', 'inventory_grant', (string) $channelId, '', is_array($before) ? $this->hydrate($before) : null, $after);

        return $after;
    }

    private function hydrate(array $row): array
    {
        return [
            'channelId' => (int) $row['channel_id'],
            'visibility' => (string) $row['visibility'],
            'canViewLedger' => (int) $row['can_view_ledger'] === 1,
            'adjustMode' => (string) $row['adjust_mode'],
            'gameIds' => $this->decodeArray($row['game_ids_json'] ?? null),
            'currencyCodes' => array_map('strtoupper', array_map('strval', $this->decodeArray($row['currency_codes_json'] ?? null))),
            'singleLimit' => (float) $row['single_limit'],
            'dailyLimit' => (float) $row['daily_limit'],
            'expiresAt' => $row['expires_at'] ?: null,
            'status' => (int) $row['status'],
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function decodeArray(mixed $value): array
    {
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function emptyGrant(int $channelId): array
    {
        return [
            'channelId' => $channelId, 'visibility' => 'none', 'canViewLedger' => false,
            'adjustMode' => 'none', 'gameIds' => [], 'currencyCodes' => [],
            'singleLimit' => 0.0, 'dailyLimit' => 0.0, 'expiresAt' => null,
            'status' => 0, 'updatedAt' => null,
        ];
    }

    private function denied(int $channelId): array
    {
        return array_merge($this->emptyGrant($channelId), ['canView' => false, 'canAdjust' => false, 'scopeAllowed' => false]);
    }

    private function assertPlatform(array $actor): void
    {
        if ((string) ($actor['role_code'] ?? '') !== 'platform_admin' || (int) ($actor['channel_id'] ?? -1) !== 0) {
            throw new RuntimeException('PLATFORM_ADMIN_REQUIRED');
        }
    }
}
