<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class RoundRepository
{
    public function paginate(?int $channelId, array $filters, int $page, int $pageSize): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('d_user u', 'u.uid=l.uid')
            ->leftJoin('s_game g', 'g.id=l.game_id')
            ->whereIn('l.event_type', ['settle', 'campaign_settle'])
            ->whereBetween('l.create_time', [$filters['from'], $filters['to']]);
        $this->applyFilters($query, $channelId, $filters);

        $total = (clone $query)->count();
        $items = $query
            ->field('l.id,l.channel_id,l.round_id,l.uid,u.playername,l.game_id,g.title,l.currency_code,l.bet,l.payout,l.amount,l.cash_before,l.cash_after,l.scene_name,l.mode_name,l.event_type,l.create_time')
            ->order('l.create_time', 'desc')->order('l.id', 'desc')
            ->page($page, $pageSize)->select()->toArray();
        foreach ($items as &$item) {
            $bet = (float) $item['bet'];
            $item['profit'] = number_format($bet - (float) $item['payout'], 4, '.', '');
            $item['win_multiplier'] = $bet > 0 ? round((float) $item['payout'] / $bet, 4) : null;
        }

        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total];
    }

    public function find(?int $channelId, int $id): ?array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('d_user u', 'u.uid=l.uid')->leftJoin('s_game g', 'g.id=l.game_id')
            ->whereIn('l.event_type', ['settle', 'campaign_settle'])
            ->where('l.id', $id);
        if ($channelId !== null) {
            $query->where('l.channel_id', $channelId);
        }
        $round = $query->field('l.*,u.playername,g.title')->find();
        if (!is_array($round)) {
            return null;
        }
        $round['detail'] = isset($round['detail']) && is_string($round['detail']) ? json_decode($round['detail'], true) : $round['detail'];

        return $round;
    }

    private function applyFilters(object $query, ?int $channelId, array $filters): void
    {
        if ($channelId !== null) $query->where('l.channel_id', $channelId);
        if (!empty($filters['uid'])) $query->where('l.uid', (int) $filters['uid']);
        if (!empty($filters['gameId'])) $query->where('l.game_id', (int) $filters['gameId']);
        if (!empty($filters['currencyCode'])) $query->where('l.currency_code', strtoupper((string) $filters['currencyCode']));
        if (!empty($filters['roundId'])) $query->where('l.round_id', (int) $filters['roundId']);
        if (!empty($filters['sceneName'])) $query->where('l.scene_name', (string) $filters['sceneName']);
        if (!empty($filters['modeName'])) $query->where('l.mode_name', (string) $filters['modeName']);
        if ($filters['minimumBet'] !== null) $query->where('l.bet', '>=', $filters['minimumBet']);
        if ($filters['minimumPayout'] !== null) $query->where('l.payout', '>=', $filters['minimumPayout']);
    }
}
