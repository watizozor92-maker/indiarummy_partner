<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class PlayerRepository
{
    private const SORTS = [
        'uid' => 'u.uid',
        'balance' => 'u.coin',
        'createdAt' => 'u.create_time',
        'lastActiveAt' => 'u.login_time',
    ];

    public function paginate(int $channelId, string $keyword, ?int $status, int $page, int $pageSize, string $sort, string $direction, string $from, string $to, ?string $currencyCode): array
    {
        $query = Db::connect('game')->table('d_user')->alias('u')->where('u.channel_agency', $channelId);
        if ($keyword !== '') {
            if (ctype_digit($keyword)) {
                $query->where(static function ($q) use ($keyword): void {
                    $q->where('u.uid', (int) $keyword)->whereOrLike('u.playername', '%' . $keyword . '%');
                });
            } else {
                $query->whereLike('u.playername', '%' . $keyword . '%');
            }
        }
        if ($status !== null) {
            $query->where('u.status', $status);
        }

        $total = (clone $query)->count();
        $items = $query
            ->field('u.uid,u.playername,u.coin,u.status,u.create_time,u.login_time,u.lastgameid')
            ->order(self::SORTS[$sort] ?? self::SORTS['lastActiveAt'], $direction === 'asc' ? 'asc' : 'desc')
            ->order('u.uid', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $uids = array_map(static fn (array $item): int => (int) $item['uid'], $items);
        $stats = [];
        if ($uids !== []) {
            $statsQuery = Db::connect('game')->table('d_slot_inventory_ledger')
                ->where('channel_id', $channelId)
                ->whereIn('uid', $uids)
                ->whereBetween('create_time', [$from, $to]);
            if ($currencyCode !== null && $currencyCode !== '') {
                $statsQuery->where('currency_code', strtoupper($currencyCode));
            }
            foreach ($statsQuery
                ->fieldRaw('uid, currency_code, SUM(bet) AS bet_amount, SUM(payout) AS payout_amount, SUM(bet-payout) AS profit_amount, COUNT(*) AS spin_count, MAX(create_time) AS last_play_at')
                ->group('uid,currency_code')
                ->select()
                ->toArray() as $row) {
                $stats[(int) $row['uid']][] = $row;
            }
        }

        foreach ($items as &$item) {
            $item['period_stats'] = $stats[(int) $item['uid']] ?? [];
        }

        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total];
    }

    public function find(int $channelId, int $uid): ?array
    {
        $player = Db::connect('game')->table('d_user')->where('channel_agency', $channelId)
            ->where('uid', $uid)
            ->field('uid,playername,coin,status,create_time,login_time,lastgameid')
            ->find();
        if (!is_array($player)) {
            return null;
        }

        $player['statistics'] = Db::connect('game')->table('d_slot_inventory_ledger')
            ->where('channel_id', $channelId)->where('uid', $uid)
            ->fieldRaw('currency_code, SUM(bet) AS bet_amount, SUM(payout) AS payout_amount, SUM(bet-payout) AS profit_amount, COUNT(*) AS spin_count, MAX(create_time) AS last_play_at')
            ->group('currency_code')->select()->toArray();
        $player['games'] = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('s_game g', 'g.id=l.game_id')
            ->where('l.channel_id', $channelId)->where('l.uid', $uid)
            ->fieldRaw('l.game_id,g.title,l.currency_code,SUM(l.bet) AS bet_amount,SUM(l.payout) AS payout_amount,SUM(l.bet-l.payout) AS profit_amount,COUNT(*) AS spin_count,MAX(l.create_time) AS last_play_at')
            ->group('l.game_id,g.title,l.currency_code')->order('last_play_at','desc')->select()->toArray();
        $player['recent_rounds'] = Db::connect('game')->table('d_slot_inventory_ledger')->alias('l')
            ->leftJoin('s_game g', 'g.id=l.game_id')
            ->where('l.channel_id', $channelId)->where('l.uid', $uid)
            ->field('l.id,l.round_id,l.game_id,g.title,l.currency_code,l.bet,l.payout,l.scene_name,l.mode_name,l.create_time')
            ->order('l.create_time','desc')->order('l.id','desc')->limit(20)->select()->toArray();
        $player['experience_states'] = Db::connect('game')->table('d_slot_experience_state')->alias('e')
            ->leftJoin('s_game g', 'g.id=e.game_id')
            ->where('e.channel_id', $channelId)->where('e.uid', $uid)
            ->field('e.channel_id,e.game_id,g.title,e.currency_code,e.cycle_id,e.assist_count,e.paid_rounds,e.total_bet,e.total_payout,e.total_expected,e.next_check_round,e.pending_round_id,e.config_version,e.cycle_started_at,e.update_time')
            ->order('e.update_time','desc')->select()->toArray();

        return $player;
    }
}
