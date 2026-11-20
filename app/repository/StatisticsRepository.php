<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class StatisticsRepository
{
    public function games(?int $channelId, string $from, string $to, ?string $currencyCode): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('s_game g', 'g.id=l.game_id')->whereBetween('l.create_time', [$from, $to]);
        if ($channelId !== null) $query->where('l.channel_id', $channelId);
        if ($currencyCode) $query->where('l.currency_code', strtoupper($currencyCode));
        $rows = $query->fieldRaw('l.game_id,g.title,l.currency_code,SUM(l.bet) bet_amount,SUM(l.payout) payout_amount,SUM(l.bet-l.payout) profit_amount,COUNT(*) spin_count,COUNT(DISTINCT l.uid) player_count,SUM(CASE WHEN l.payout>0 THEN 1 ELSE 0 END) payout_hits,SUM(CASE WHEN l.payout>l.bet THEN 1 ELSE 0 END) net_win_hits,MAX(l.payout) max_payout')
            ->group('l.game_id,g.title,l.currency_code')->order('bet_amount','desc')->select()->toArray();

        return array_map([$this, 'rates'], $rows);
    }

    public function merchants(string $from, string $to, ?string $currencyCode): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('d_channel_parter c', 'c.id=l.channel_id')->whereBetween('l.create_time', [$from, $to]);
        if ($currencyCode) $query->where('l.currency_code', strtoupper($currencyCode));
        $rows = $query->fieldRaw('l.channel_id,c.agency_uid,c.show_name,l.currency_code,SUM(l.bet) bet_amount,SUM(l.payout) payout_amount,SUM(l.bet-l.payout) profit_amount,COUNT(*) spin_count,COUNT(DISTINCT l.uid) player_count,SUM(CASE WHEN l.payout>0 THEN 1 ELSE 0 END) payout_hits,SUM(CASE WHEN l.payout>l.bet THEN 1 ELSE 0 END) net_win_hits,MAX(l.payout) max_payout')
            ->group('l.channel_id,c.agency_uid,c.show_name,l.currency_code')->order('bet_amount','desc')->select()->toArray();

        return array_map([$this, 'rates'], $rows);
    }

    private function rates(array $row): array
    {
        $bet=(float)$row['bet_amount']; $spins=(int)$row['spin_count'];
        $row['actual_rtp']=$bet>0?round((float)$row['payout_amount']/$bet,6):null;
        $row['payout_hit_rate']=$spins>0?round((int)$row['payout_hits']/$spins,6):null;
        $row['net_win_hit_rate']=$spins>0?round((int)$row['net_win_hits']/$spins,6):null;
        unset($row['payout_hits'],$row['net_win_hits']);
        return $row;
    }
}
