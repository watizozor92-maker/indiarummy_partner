<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class OverviewRepository
{
    public function summary(?int $channelId, string $from, string $to, ?string $currencyCode = null, ?int $gameId = null): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')
            ->whereBetween('create_time', [$from, $to]);
        $this->scope($query, $channelId, $currencyCode, $gameId);

        $rows = $query
            ->fieldRaw('currency_code, SUM(bet) AS bet_amount, SUM(payout) AS payout_amount, SUM(bet - payout) AS profit_amount, COUNT(*) AS spin_count, COUNT(DISTINCT uid) AS player_count, SUM(CASE WHEN payout > 0 THEN 1 ELSE 0 END) AS payout_hit_count, SUM(CASE WHEN payout > bet THEN 1 ELSE 0 END) AS net_win_count')
            ->group('currency_code')
            ->order('currency_code')
            ->select()
            ->toArray();

        return array_map([$this, 'normalizeSummary'], $rows);
    }

    public function trend(?int $channelId, string $from, string $to, ?string $currencyCode = null, ?int $gameId = null): array
    {
        $query = Db::connect('game')->table('d_slot_inventory_ledger')
            ->whereBetween('create_time', [$from, $to]);
        $this->scope($query, $channelId, $currencyCode, $gameId);

        return $query
            ->fieldRaw('DATE(create_time) AS stat_date, currency_code, SUM(bet) AS bet_amount, SUM(payout) AS payout_amount, SUM(bet - payout) AS profit_amount, COUNT(*) AS spin_count, COUNT(DISTINCT uid) AS player_count')
            ->group('DATE(create_time), currency_code')
            ->order('stat_date')
            ->order('currency_code')
            ->select()
            ->toArray();
    }

    private function scope(object $query, ?int $channelId, ?string $currencyCode, ?int $gameId): void
    {
        if ($channelId !== null) {
            $query->where('channel_id', $channelId);
        }
        if ($currencyCode !== null && $currencyCode !== '') {
            $query->where('currency_code', strtoupper($currencyCode));
        }
        if ($gameId !== null && $gameId > 0) {
            $query->where('game_id', $gameId);
        }
    }

    private function normalizeSummary(array $row): array
    {
        $bet = (string) ($row['bet_amount'] ?? '0');
        $payout = (string) ($row['payout_amount'] ?? '0');
        $spins = (int) ($row['spin_count'] ?? 0);

        return [
            'currencyCode' => (string) $row['currency_code'],
            'betAmount' => $bet,
            'payoutAmount' => $payout,
            'profitAmount' => (string) ($row['profit_amount'] ?? '0'),
            'spinCount' => $spins,
            'playerCount' => (int) ($row['player_count'] ?? 0),
            'actualRtp' => (float) $bet > 0 ? round((float) $payout / (float) $bet, 6) : null,
            'payoutHitRate' => $spins > 0 ? round((int) $row['payout_hit_count'] / $spins, 6) : null,
            'netWinHitRate' => $spins > 0 ? round((int) $row['net_win_count'] / $spins, 6) : null,
        ];
    }
}
