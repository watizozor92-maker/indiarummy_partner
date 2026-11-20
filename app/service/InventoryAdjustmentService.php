<?php

declare(strict_types=1);

namespace app\service;

use app\repository\InventoryRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class InventoryAdjustmentService
{
    public function __construct(
        private readonly InventoryGrantService $grants,
        private readonly InventoryAccessPolicy $policy,
        private readonly InventoryRepository $inventories,
        private readonly InternalGameApiClient $runtime,
        private readonly AuditService $audit,
    ) {
    }

    public function paginate(array $actor, ?string $status, int $page, int $pageSize): array
    {
        $query = Db::connect('partner')->table('partner_inventory_adjustment')->alias('a');
        if (!$this->isPlatform($actor)) $query->where('a.channel_id', (int) $actor['channel_id']);
        if ($status !== null && $status !== '') $query->where('a.status', strtolower($status));
        $total = (clone $query)->count();
        $items = $query->field('a.*')->order('a.created_at', 'desc')->order('a.id', 'desc')
            ->page($page, $pageSize)->select()->toArray();
        $channelIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['channel_id'], $items)));
        $merchants = $channelIds === [] ? [] : Db::connect('game')->table('d_channel_parter')->whereIn('id', $channelIds)
            ->column('show_name', 'id');
        foreach ($items as &$item) $item['show_name'] = $merchants[(int) $item['channel_id']] ?? '';

        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total];
    }

    public function create(array $input, array $actor, string $ip, string $agent): array
    {
        $channelId = $this->isPlatform($actor) ? (int) ($input['channelId'] ?? 0) : (int) $actor['channel_id'];
        $gameId = (int) ($input['gameId'] ?? 0);
        $currency = strtoupper(trim((string) ($input['currencyCode'] ?? '')));
        $type = strtolower(trim((string) ($input['adjustmentType'] ?? '')));
        $amount = $this->amount($input['amount'] ?? null);
        $reason = trim((string) ($input['reason'] ?? ''));
        $requestKey = trim((string) ($input['requestKey'] ?? ''));
        if ($channelId <= 0 || $gameId <= 0 || !preg_match('/^[A-Z0-9]{2,12}$/', $currency)) {
            throw new InvalidArgumentException('商户、游戏和币种不能为空');
        }
        if (!in_array($type, ['topup', 'withdraw'], true)) throw new InvalidArgumentException('调整类型无效');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) throw new InvalidArgumentException('操作原因必须为 5 到 255 个字符');
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestKey)) throw new InvalidArgumentException('请求标识无效');

        $snapshot = $this->inventories->findSnapshot($channelId, $gameId, $currency);
        if ($snapshot === null) throw new InvalidArgumentException('所选库存尚未初始化');
        $access = $this->grants->effectiveFor($actor, $channelId, $gameId, $currency);
        if (!$this->isPlatform($actor) && empty($access['canAdjust'])) throw new RuntimeException('INVENTORY_ADJUST_FORBIDDEN');

        $mode = $this->isPlatform($actor) ? 'platform' : (string) $access['adjustMode'];
        $execute = $mode === 'platform';
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $partner = Db::connect('partner');
        try {
            $id = (int) $partner->transaction(function ($db) use (&$execute, $mode, $access, $amount, $channelId, $gameId,
                $currency, $requestKey, $type, $snapshot, $reason, $actor, $ip, $now): int {
                if ($mode === 'direct') {
                    $db->table('partner_inventory_grant')->where('channel_id', $channelId)->lock(true)->find();
                    $dailyUsed = (float) $db->table('partner_inventory_adjustment')
                        ->where(['channel_id' => $channelId, 'game_id' => $gameId, 'currency_code' => $currency])
                        ->whereIn('status', ['processing', 'executed'])->where('execution_mode', 'direct')
                        ->where('created_at', '>=', date('Y-m-d 00:00:00'))->sum('amount');
                    $execute = $this->policy->canDirect($amount, $dailyUsed, (float) $access['singleLimit'], (float) $access['dailyLimit']);
                }
                return (int) $db->table('partner_inventory_adjustment')->insertGetId([
                    'request_key' => $requestKey, 'channel_id' => $channelId, 'game_id' => $gameId,
                    'currency_code' => $currency, 'adjustment_type' => $type,
                    'amount' => number_format($amount, 4, '.', ''), 'cash_before' => $snapshot['cash_reserve'],
                    'cash_after' => null, 'reason' => $reason, 'status' => $execute ? 'processing' : 'pending',
                    'execution_mode' => $execute ? $mode : 'request', 'requested_by' => (int) $actor['id'],
                    'requested_name' => mb_substr((string) ($actor['display_name'] ?? ''), 0, 64), 'requested_ip' => mb_substr($ip, 0, 64),
                    'reviewed_by' => null, 'reviewed_name' => '', 'review_reason' => '', 'runtime_error' => '',
                    'reviewed_at' => null, 'executed_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate entry')) {
                $existing = Db::connect('partner')->table('partner_inventory_adjustment')->where([
                    'requested_by' => (int) $actor['id'], 'request_key' => $requestKey,
                ])->find();
                if (is_array($existing)) return $existing;
            }
            throw $exception;
        }

        $result = $execute ? $this->execute($id, $actor, $ip) : $this->find($id);
        $this->audit->record('inventory.adjustment.create', $result['status'] === 'failed' ? 'failed' : 'success', $ip, $agent,
            (int) $actor['id'], (int) $actor['channel_id'], (string) $actor['role_code'], 'inventory_adjustment', (string) $id,
            $reason, null, $result, ['directRequested' => $mode === 'direct', 'executedImmediately' => $execute]);

        return $result;
    }

    public function approve(int $id, string $reviewReason, array $actor, string $ip, string $agent): array
    {
        $this->assertPlatform($actor);
        $row = $this->find($id);
        if ($row['status'] !== 'pending') throw new RuntimeException('ADJUSTMENT_NOT_PENDING');
        if (mb_strlen(trim($reviewReason)) < 2) throw new InvalidArgumentException('请填写审批说明');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $affected = Db::connect('partner')->table('partner_inventory_adjustment')->where(['id' => $id, 'status' => 'pending'])->update([
            'status' => 'processing', 'reviewed_by' => (int) $actor['id'], 'reviewed_name' => (string) $actor['display_name'],
            'review_reason' => mb_substr(trim($reviewReason), 0, 255), 'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        if ($affected !== 1) throw new RuntimeException('ADJUSTMENT_NOT_PENDING');
        $result = $this->execute($id, $actor, $ip);
        $this->audit->record('inventory.adjustment.approve', $result['status'] === 'failed' ? 'failed' : 'success', $ip, $agent,
            (int) $actor['id'], 0, 'platform_admin', 'inventory_adjustment', (string) $id, $reviewReason, $row, $result);

        return $result;
    }

    public function reject(int $id, string $reviewReason, array $actor, string $ip, string $agent): array
    {
        $this->assertPlatform($actor);
        $row = $this->find($id);
        if ($row['status'] !== 'pending') throw new RuntimeException('ADJUSTMENT_NOT_PENDING');
        if (mb_strlen(trim($reviewReason)) < 2) throw new InvalidArgumentException('请填写拒绝原因');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $affected = Db::connect('partner')->table('partner_inventory_adjustment')->where(['id' => $id, 'status' => 'pending'])->update([
            'status' => 'rejected', 'reviewed_by' => (int) $actor['id'], 'reviewed_name' => (string) $actor['display_name'],
            'review_reason' => mb_substr(trim($reviewReason), 0, 255), 'reviewed_at' => $now, 'updated_at' => $now,
        ]);
        if ($affected !== 1) throw new RuntimeException('ADJUSTMENT_NOT_PENDING');
        $result = $this->find($id);
        $this->audit->record('inventory.adjustment.reject', 'success', $ip, $agent, (int) $actor['id'], 0,
            'platform_admin', 'inventory_adjustment', (string) $id, $reviewReason, $row, $result);

        return $result;
    }

    private function execute(int $id, array $operator, string $ip): array
    {
        $row = $this->find($id);
        $delta = (float) $row['amount'] * ($row['adjustment_type'] === 'withdraw' ? -1 : 1);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        try {
            $response = $this->runtime->adjustCashReserve((int) $row['channel_id'], (int) $row['game_id'],
                (string) $row['currency_code'], $delta, [
                    'id' => (int) $operator['id'], 'name' => (string) $operator['display_name'], 'ip' => $ip,
                    'reason' => (string) $row['reason'], 'source' => 'partner_inventory_adjustment',
                ]);
            $snapshot = isset($response['inventory']) && is_array($response['inventory']) ? $response['inventory'] : $response;
            $before = $response['cashBefore'] ?? $snapshot['cashBefore'] ?? $row['cash_before'];
            $after = $response['cashAfter'] ?? $snapshot['cashReserve'] ?? $snapshot['cashAfter'] ?? ((float) $before + $delta);
            Db::connect('partner')->table('partner_inventory_adjustment')->where('id', $id)->update([
                'status' => 'executed', 'cash_before' => $before, 'cash_after' => $after,
                'runtime_error' => '', 'executed_at' => $now, 'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            Db::connect('partner')->table('partner_inventory_adjustment')->where('id', $id)->update([
                'status' => 'failed', 'runtime_error' => mb_substr($exception->getMessage(), 0, 500), 'updated_at' => $now,
            ]);
        }

        return $this->find($id);
    }

    private function find(int $id): array
    {
        $row = Db::connect('partner')->table('partner_inventory_adjustment')->where('id', $id)->find();
        if (!is_array($row)) throw new RuntimeException('ADJUSTMENT_NOT_FOUND');
        return $row;
    }

    private function amount(mixed $value): float
    {
        $text = trim((string) $value);
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $text) || (float) $text <= 0) {
            throw new InvalidArgumentException('调整金额必须为最多四位小数的正数');
        }
        return (float) $text;
    }

    private function isPlatform(array $actor): bool
    {
        return (string) ($actor['role_code'] ?? '') === 'platform_admin' && (int) ($actor['channel_id'] ?? -1) === 0;
    }

    private function assertPlatform(array $actor): void
    {
        if (!$this->isPlatform($actor)) throw new RuntimeException('PLATFORM_ADMIN_REQUIRED');
    }
}
