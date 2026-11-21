<?php
declare(strict_types=1);
namespace app\service;
use DateTimeImmutable;use InvalidArgumentException;use think\facade\Db;
final class ControlGrantService
{
    public function __construct(private readonly AuditService $audit){}
    public function list(int $channel):array
    {
        $partner=Db::connect('partner');$grants=[];foreach($partner->table('partner_control_preset_grant')->where('channel_id',$channel)->select()->toArray() as $row)$grants[(int)$row['game_id'].':'.(string)$row['preset_key']]=$row;
        $presets=Db::connect('game')->table('s_slot_control_preset')->alias('p')->leftJoin('s_game g','g.id=p.game_id')->where('p.status',1)->field('p.game_id,g.title,p.preset_key,p.preset_name,p.description,p.version,p.settings_json,p.metrics_json')->order('p.game_id')->order('p.preset_key')->select()->toArray();
        foreach($presets as &$preset){$grant=$grants[(int)$preset['game_id'].':'.(string)$preset['preset_key']]??null;$settings=json_decode((string)$preset['settings_json'],true)?:[];$metrics=json_decode((string)$preset['metrics_json'],true)?:[];$stable=$metrics['stable']??reset($metrics)?:[];$preset['target_rtp']=$settings['targetRtp']??$settings['stableRtp']??null;$preset['hit_rate']=$stable['hitRate']??null;$preset['status']=is_array($grant)?(int)$grant['status']:0;$preset['updated_at']=is_array($grant)?$grant['updated_at']:null;unset($preset['settings_json'],$preset['metrics_json']);}
        $scopes=[];foreach($partner->table('partner_game_control_scope')->where('channel_id',$channel)->select()->toArray() as $row)$scopes[(int)$row['game_id']]=$this->normalizeScope($row);
        foreach(array_values(array_unique(array_map(static fn(array $x):int=>(int)$x['game_id'],$presets))) as $gameId)$scopes[$gameId]??=$this->defaultScope($channel,$gameId);
        return ['presets'=>$presets,'scopes'=>array_values($scopes)];
    }
    public function set(int $channel,int $game,string $preset,int $status,array $actor,string $ip,string $agent):void{$exists=Db::connect('game')->table('s_slot_control_preset')->where(['game_id'=>$game,'preset_key'=>$preset,'status'=>1])->find();if(!is_array($exists))throw new InvalidArgumentException('控制预设不存在或未启用');$db=Db::connect('partner');$row=$db->table('partner_control_preset_grant')->where(['channel_id'=>$channel,'game_id'=>$game,'preset_key'=>$preset])->find();$now=(new DateTimeImmutable())->format('Y-m-d H:i:s');$values=['status'=>$status===1?1:0,'updated_at'=>$now];if(is_array($row))$db->table('partner_control_preset_grant')->where('id',(int)$row['id'])->update($values);else{$values+=['channel_id'=>$channel,'game_id'=>$game,'preset_key'=>$preset,'created_by'=>(int)$actor['id'],'created_at'=>$now];$db->table('partner_control_preset_grant')->insert($values);}$this->audit->record('game_control.grant','success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'control_grant',$channel.':'.$game.':'.$preset,'',$row?:null,$values);}

    public function scope(int $channel,int $game):array
    {
        $row=Db::connect('partner')->table('partner_game_control_scope')->where(['channel_id'=>$channel,'game_id'=>$game])->find();
        return is_array($row)?$this->normalizeScope($row):$this->defaultScope($channel,$game);
    }

    public function saveScope(int $channel,int $game,array $input,array $actor,string $ip,string $agent):array
    {
        if((string)($actor['role_code']??'')!=='platform_admin')throw new InvalidArgumentException('仅平台管理员可以设置调控范围');
        if($channel<=0||!Db::connect('game')->table('d_channel_parter')->where('id',$channel)->find())throw new InvalidArgumentException('商户不存在');
        if($game<=0||!Db::connect('game')->table('s_game')->where('id',$game)->find())throw new InvalidArgumentException('游戏不存在');
        $rtpMin=$this->percent($input['rtpMin']??null,'RTP 最小值');$rtpMax=$this->percent($input['rtpMax']??null,'RTP 最大值');$rtpStep=$this->percent($input['rtpStep']??null,'RTP 步长');
        $hitMin=$this->percent($input['hitMin']??null,'中奖率最小值');$hitMax=$this->percent($input['hitMax']??null,'中奖率最大值');$hitStep=$this->percent($input['hitStep']??null,'中奖率步长');
        if($rtpMin<=0||$rtpMax>2||$rtpMin>$rtpMax||$rtpStep<=0)throw new InvalidArgumentException('RTP 授权范围无效');
        if($hitMin<0||$hitMax>1||$hitMin>$hitMax||$hitStep<=0)throw new InvalidArgumentException('中奖率授权范围无效');
        $db=Db::connect('partner');$before=$db->table('partner_game_control_scope')->where(['channel_id'=>$channel,'game_id'=>$game])->find();$now=(new DateTimeImmutable())->format('Y-m-d H:i:s');
        $values=['allow_custom'=>(int)($input['allowCustom']??0)===1?1:0,'rtp_min'=>$rtpMin,'rtp_max'=>$rtpMax,'rtp_step'=>$rtpStep,'hit_min'=>$hitMin,'hit_max'=>$hitMax,'hit_step'=>$hitStep,'allow_rollback'=>(int)($input['allowRollback']??0)===1?1:0,'status'=>(int)($input['status']??0)===1?1:0,'updated_at'=>$now];
        if(is_array($before))$db->table('partner_game_control_scope')->where(['channel_id'=>$channel,'game_id'=>$game])->update($values);else{$values+=['channel_id'=>$channel,'game_id'=>$game,'created_by'=>(int)$actor['id'],'created_at'=>$now];$db->table('partner_game_control_scope')->insert($values);}
        $after=$this->scope($channel,$game);$this->audit->record('game_control.scope.update','success',$ip,$agent,(int)$actor['id'],0,'platform_admin','control_scope',$channel.':'.$game,'',$before?:null,$after);return $after;
    }

    public function allowedPresetKeys(array $actor,int $channel,int $game):array
    {
        if((string)($actor['role_code']??'')==='platform_admin')return array_map('strval',Db::connect('game')->table('s_slot_control_preset')->where(['game_id'=>$game,'status'=>1])->column('preset_key'));
        return array_map('strval',Db::connect('partner')->table('partner_control_preset_grant')->where(['channel_id'=>$channel,'game_id'=>$game,'status'=>1])->column('preset_key'));
    }

    private function percent(mixed $value,string $label):float
    {
        if(!is_numeric($value))throw new InvalidArgumentException($label.'必须是数字');return round((float)$value/100,6);
    }
    private function normalizeScope(array $row):array{return ['channel_id'=>(int)$row['channel_id'],'game_id'=>(int)$row['game_id'],'allow_custom'=>(int)$row['allow_custom'],'rtp_min'=>(float)$row['rtp_min'],'rtp_max'=>(float)$row['rtp_max'],'rtp_step'=>(float)$row['rtp_step'],'hit_min'=>(float)$row['hit_min'],'hit_max'=>(float)$row['hit_max'],'hit_step'=>(float)$row['hit_step'],'allow_rollback'=>(int)$row['allow_rollback'],'status'=>(int)$row['status']];}
    private function defaultScope(int $channel,int $game):array{return ['channel_id'=>$channel,'game_id'=>$game,'allow_custom'=>0,'rtp_min'=>0.9,'rtp_max'=>1.0,'rtp_step'=>0.001,'hit_min'=>0.05,'hit_max'=>0.5,'hit_step'=>0.005,'allow_rollback'=>0,'status'=>1];}
}
