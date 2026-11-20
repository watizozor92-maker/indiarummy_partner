<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class InventoryRepository
{
    public function overview(?int $channelId, array $gameIds = [], array $currencyCodes = []): array
    {
        $query = Db::connect('game')->table('d_slot_inventory')->alias('i')
            ->leftJoin('d_channel_parter c', 'c.id=i.channel_id')
            ->leftJoin('s_game g', 'g.id=i.game_id');
        if ($channelId !== null) $query->where('i.channel_id', $channelId);
        if ($gameIds !== []) $query->whereIn('i.game_id', $gameIds);
        if ($currencyCodes !== []) $query->whereIn('i.currency_code', $currencyCodes);
        $rows = $query->fieldRaw('i.channel_id,c.agency_uid,c.show_name,i.game_id,g.title,i.currency_code,i.cash_reserve,i.target_reserve,i.experience_budget,i.assist_reserved,i.campaign_available,i.campaign_reserved,i.pending_liability,i.realized_profit,i.period_bet,i.period_payout,i.period_spins,i.period_started_at,i.version,i.update_time,(i.cash_reserve-i.campaign_available-i.campaign_reserved-i.pending_liability) AS available_reserve')
            ->order('i.channel_id')->order('i.game_id')->order('i.currency_code')->select()->toArray();
        foreach ($rows as &$row) {
            $target = (float) $row['target_reserve'];
            $available = (float) $row['available_reserve'];
            $row['health_ratio'] = $target > 0 ? $available / $target : null;
            $row['health_status'] = $target <= 0 ? 'unconfigured' : ($available < 0 ? 'danger' : ($available < $target * 0.6 ? 'danger' : ($available < $target ? 'warning' : 'healthy')));
        }

        return $rows;
    }

    public function ledger(?int $channelId, array $filters, int $page, int $pageSize, array $gameIds = [], array $currencyCodes = []): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('d_channel_parter c', 'c.id=l.channel_id')
            ->leftJoin('s_game g', 'g.id=l.game_id')
            ->whereBetween('l.create_time', [$filters['from'], $filters['to']]);
        if ($channelId !== null) $query->where('l.channel_id', $channelId);
        if ($gameIds !== []) $query->whereIn('l.game_id', $gameIds);
        if ($currencyCodes !== []) $query->whereIn('l.currency_code', $currencyCodes);
        if (!empty($filters['gameId'])) $query->where('l.game_id', (int) $filters['gameId']);
        if (!empty($filters['currencyCode'])) $query->where('l.currency_code', strtoupper((string) $filters['currencyCode']));
        if (!empty($filters['eventType'])) $query->where('l.event_type', (string) $filters['eventType']);
        if (!empty($filters['uid'])) $query->where('l.uid', (int) $filters['uid']);
        $total = (clone $query)->count();
        $items = $query->fieldRaw('l.id,l.channel_id,c.show_name,c.agency_uid,l.game_id,g.title,l.currency_code,l.uid,l.round_id,l.event_type,l.bet,l.payout,l.amount,l.cash_before,l.cash_after,(l.cash_after-l.cash_before) AS cash_change,l.scene_name,l.mode_name,l.campaign_id,l.create_time')
            ->order('l.create_time', 'desc')->order('l.id', 'desc')->page($page, $pageSize)->select()->toArray();

        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total];
    }

    public function findSnapshot(int $channelId, int $gameId, string $currencyCode): ?array
    {
        $row = Db::connect('game')->table('d_slot_inventory')->where([
            'channel_id' => $channelId,
            'game_id' => $gameId,
            'currency_code' => strtoupper($currencyCode),
        ])->find();

        return is_array($row) ? $row : null;
    }
}
