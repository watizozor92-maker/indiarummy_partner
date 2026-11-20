<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class PlayerControlService
{
    private const MODES=['SUPPORT','SUPPRESS','RTP_TARGET'];
    private const SUPPORTED_GAMES=[419,541,635,692,698,699];
    public function __construct(private readonly InternalGameApiClient $runtime,private readonly AuditService $audit){}

    public function create(int $channelId,array $input,array $actor,string $ip,string $agent):array
    {
        if($channelId<=0)throw new InvalidArgumentException('玩家调控必须选择商户');
        $uid=(int)($input['uid']??0);$gameId=(int)($input['gameId']??0);$currency=strtoupper(trim((string)($input['currencyCode']??'')));$mode=strtoupper(trim((string)($input['mode']??'')));
        $target=round((float)($input['targetAmount']??0),4);$targetRtp=($input['targetRtp']??'')===''?null:(float)$input['targetRtp'];$maxRounds=(int)($input['maxRounds']??0);$reason=trim((string)($input['reason']??''));
        $startAt=strtotime((string)($input['startAt']??''));$endAt=strtotime((string)($input['endAt']??''));
        if($uid<=0||$gameId<=0||$currency===''||!in_array($mode,self::MODES,true))throw new InvalidArgumentException('玩家、游戏、币种和调控模式不能为空');
        if(!in_array($gameId,self::SUPPORTED_GAMES,true))throw new InvalidArgumentException('该游戏尚未完成玩家调控结算适配');
        if($target<=0||$target>100000000)throw new InvalidArgumentException('目标金额必须大于 0 且不超过 100000000');
        if($mode==='RTP_TARGET'&&($targetRtp===null||$targetRtp<0.1||$targetRtp>2))throw new InvalidArgumentException('目标 RTP 必须在 10% 到 200% 之间');
        if($maxRounds<1||$maxRounds>10000)throw new InvalidArgumentException('最大局数必须在 1 到 10000 之间');
        if($startAt===false||$endAt===false||$endAt<=$startAt||$endAt-$startAt>31*86400)throw new InvalidArgumentException('有效期无效或超过 31 天');
        if(mb_strlen($reason)<5||mb_strlen($reason)>255)throw new InvalidArgumentException('原因必须为 5 到 255 个字符');
        $game=Db::connect('game');
        if(!$game->table('d_user')->where(['uid'=>$uid,'channel_agency'=>$channelId])->find())throw new InvalidArgumentException('玩家不存在或不属于该商户');
        if(!$game->table('s_channel_currency')->where(['channel_id'=>$channelId,'currency_code'=>$currency,'status'=>1])->find())throw new InvalidArgumentException('该商户未启用所选币种');
        if(!$game->table('s_slot_control_profile')->where(['channel_id'=>0,'game_id'=>$gameId,'status'=>1])->find())throw new InvalidArgumentException('该游戏尚未接入统一调控');
        if($game->table('d_slot_player_control')->where(['channel_id'=>$channelId,'uid'=>$uid,'game_id'=>$gameId,'currency_code'=>$currency,'state'=>'ACTIVE'])->find())throw new RuntimeException('PLAYER_CONTROL_CONFLICT');
        try{$id=(int)$game->table('d_slot_player_control')->insertGetId(['channel_id'=>$channelId,'uid'=>$uid,'game_id'=>$gameId,'currency_code'=>$currency,'control_mode'=>$mode,'target_amount'=>$target,'target_rtp'=>$targetRtp,'max_rounds'=>$maxRounds,'completed_rounds'=>0,'total_bet'=>0,'total_payout'=>0,'start_at'=>$startAt,'end_at'=>$endAt,'state'=>'ACTIVE','priority'=>50,'version'=>1,'load_status'=>'pending','load_error'=>'','reason'=>$reason,'operator_id'=>(int)$actor['id'],'operator_name'=>(string)$actor['display_name'],'operator_ip'=>$ip,'last_round_id'=>0,'completed_at'=>0]);}
        catch(\Throwable $e){if(str_contains($e->getMessage(),'Duplicate entry'))throw new RuntimeException('PLAYER_CONTROL_CONFLICT',0,$e);throw $e;}
        $load=$this->reload($channelId,$uid,$gameId,$currency);$game->table('d_slot_player_control')->where('control_id',$id)->update(['load_status'=>$load['status'],'load_error'=>$load['error']]);
        $after=['controlId'=>$id,'channelId'=>$channelId,'uid'=>$uid,'gameId'=>$gameId,'currencyCode'=>$currency,'mode'=>$mode,'targetAmount'=>$target,'targetRtp'=>$targetRtp,'maxRounds'=>$maxRounds,'startAt'=>$startAt,'endAt'=>$endAt,'loadStatus'=>$load['status'],'loadError'=>$load['error']];
        $this->audit->record('player_control.create',$load['status']==='failed'?'failed':'success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'player_control',(string)$id,$reason,null,$after);
        return $after;
    }

    public function cancel(int $channelId,int $controlId,string $reason,array $actor,string $ip,string $agent):array
    {
        if(mb_strlen(trim($reason))<5)throw new InvalidArgumentException('取消原因至少 5 个字符');$db=Db::connect('game');$row=$db->table('d_slot_player_control')->where(['control_id'=>$controlId,'channel_id'=>$channelId])->find();
        if(!is_array($row))throw new RuntimeException('RESOURCE_NOT_FOUND');if((string)$row['state']!=='ACTIVE')throw new RuntimeException('CONTROL_NOT_ACTIVE');
        $db->table('d_slot_player_control')->where('control_id',$controlId)->update(['state'=>'CANCELED','completed_at'=>time(),'version'=>(int)$row['version']+1,'reason'=>mb_substr((string)$row['reason'].' | 取消：'.trim($reason),0,255),'load_status'=>'pending']);
        $load=$this->reload($channelId,(int)$row['uid'],(int)$row['game_id'],(string)$row['currency_code']);$db->table('d_slot_player_control')->where('control_id',$controlId)->update(['load_status'=>$load['status'],'load_error'=>$load['error']]);
        $after=['controlId'=>$controlId,'state'=>'CANCELED','loadStatus'=>$load['status'],'loadError'=>$load['error']];$this->audit->record('player_control.cancel',$load['status']==='failed'?'failed':'success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'player_control',(string)$controlId,$reason,$row,$after);return $after;
    }

    private function reload(int $channelId,int $uid,int $gameId,string $currency):array
    {
        if(!$this->runtime->isConfigured())return ['status'=>'pending','error'=>'内部游戏管理 API 尚未配置，游戏服务启动后加载'];
        try{$result=$this->runtime->reloadPlayerControl($channelId,$uid,$gameId,$currency);$loaded=!empty($result['success'])||($result['message']??'')==='succ';return ['status'=>$loaded?'loaded':'failed','error'=>$loaded?'':'运行端未确认加载'];}catch(\Throwable $e){return ['status'=>'failed','error'=>$e->getMessage()];}
    }
}
