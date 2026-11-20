<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class PlayerControlRepository
{
    private const SUPPORTED_GAME_IDS=[419,541,635,692,698,699];
    public function paginate(?int $channelId, array $filters, int $page, int $pageSize): array
    {
        $query = Db::connect('game')->table('d_slot_player_control')->alias('c')
            ->leftJoin('d_user u', 'u.uid=c.uid')->leftJoin('s_game g', 'g.id=c.game_id');
        if ($channelId !== null) $query->where('c.channel_id', $channelId);
        if (!empty($filters['uid'])) $query->where('c.uid', (int) $filters['uid']);
        if (!empty($filters['gameId'])) $query->where('c.game_id', (int) $filters['gameId']);
        if (!empty($filters['currencyCode'])) $query->where('c.currency_code', strtoupper((string) $filters['currencyCode']));
        if (!empty($filters['state'])) $query->where('c.state', strtoupper((string) $filters['state']));
        if (!empty($filters['mode'])) $query->where('c.control_mode', strtoupper((string) $filters['mode']));
        $total = (clone $query)->count();
        $items = $query->field('c.*,u.playername,g.title')->order('c.create_time','desc')->order('c.control_id','desc')
            ->page($page,$pageSize)->select()->toArray();
        foreach ($items as &$item) $item['progress'] = $this->progress($item);
        return ['items'=>$items,'page'=>$page,'pageSize'=>$pageSize,'total'=>$total,'summary'=>$this->summary($channelId)];
    }

    public function find(?int $channelId, int $controlId): ?array
    {
        $query=Db::connect('game')->table('d_slot_player_control')->alias('c')->leftJoin('d_user u','u.uid=c.uid')->leftJoin('s_game g','g.id=c.game_id')->where('c.control_id',$controlId);
        if($channelId!==null)$query->where('c.channel_id',$channelId);
        $row=$query->field('c.*,u.playername,g.title')->find();
        if(!is_array($row))return null;
        $row['progress']=$this->progress($row);
        $row['events']=Db::connect('game')->table('d_slot_player_control_event')->where('control_id',$controlId)->order('event_id','desc')->limit(200)->select()->toArray();
        return $row;
    }

    public function games(): array
    {
        return Db::connect('game')->table('s_slot_control_profile')->alias('p')->leftJoin('s_game g','g.id=p.game_id')->where(['p.channel_id'=>0,'p.status'=>1])->whereIn('p.game_id',self::SUPPORTED_GAME_IDS)->field('p.game_id,g.title')->order('p.game_id')->select()->toArray();
    }

    public function currencies(?int $channelId): array
    {
        if ($channelId === null || $channelId <= 0) return [];
        return Db::connect('game')->table('s_channel_currency')->where([
            'channel_id'=>$channelId,'status'=>1,
        ])->field('currency_code,is_default')->order('is_default','desc')->order('currency_code')->select()->toArray();
    }

    public function supportedGameIds(): array{return self::SUPPORTED_GAME_IDS;}

    private function summary(?int $channelId): array
    {
        $query=Db::connect('game')->table('d_slot_player_control');if($channelId!==null)$query->where('channel_id',$channelId);
        $rows=$query->fieldRaw('state,COUNT(*) AS total')->group('state')->select()->toArray();$result=['ACTIVE'=>0,'COMPLETED'=>0,'CANCELED'=>0,'EXPIRED'=>0];
        foreach($rows as $row)$result[(string)$row['state']]=(int)$row['total'];return $result;
    }

    private function progress(array $row): array
    {
        $bet=(float)$row['total_bet'];$payout=(float)$row['total_payout'];$mode=(string)$row['control_mode'];
        $amount=$mode==='SUPPRESS'?max(0,$bet-$payout):max(0,$payout-$bet);$target=(float)$row['target_amount'];
        return ['amount'=>round($amount,4),'amountRatio'=>$target>0?min(1,round($amount/$target,6)):null,'roundRatio'=>(int)$row['max_rounds']>0?min(1,round((int)$row['completed_rounds']/(int)$row['max_rounds'],6)):null,'actualRtp'=>$bet>0?round($payout/$bet,6):null];
    }
}
