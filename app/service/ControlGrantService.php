<?php
declare(strict_types=1);
namespace app\service;
use DateTimeImmutable;use InvalidArgumentException;use think\facade\Db;
final class ControlGrantService
{
    public function __construct(private readonly AuditService $audit){}
    public function list(int $channel):array
    {
        $grants=[];foreach(Db::connect('partner')->table('partner_control_preset_grant')->where('channel_id',$channel)->select()->toArray() as $row)$grants[(int)$row['game_id'].':'.(string)$row['preset_key']]=$row;
        $presets=Db::connect('game')->table('s_slot_control_preset')->alias('p')->leftJoin('s_game g','g.id=p.game_id')->where('p.status',1)->field('p.game_id,g.title,p.preset_key,p.preset_name,p.description,p.version')->order('p.game_id')->order('p.preset_key')->select()->toArray();
        foreach($presets as &$preset){$grant=$grants[(int)$preset['game_id'].':'.(string)$preset['preset_key']]??null;$preset['status']=is_array($grant)?(int)$grant['status']:0;$preset['updated_at']=is_array($grant)?$grant['updated_at']:null;}
        return $presets;
    }
    public function set(int $channel,int $game,string $preset,int $status,array $actor,string $ip,string $agent):void{$exists=Db::connect('game')->table('s_slot_control_preset')->where(['game_id'=>$game,'preset_key'=>$preset,'status'=>1])->find();if(!is_array($exists))throw new InvalidArgumentException('控制预设不存在或未启用');$db=Db::connect('partner');$row=$db->table('partner_control_preset_grant')->where(['channel_id'=>$channel,'game_id'=>$game,'preset_key'=>$preset])->find();$now=(new DateTimeImmutable())->format('Y-m-d H:i:s');$values=['status'=>$status===1?1:0,'updated_at'=>$now];if(is_array($row))$db->table('partner_control_preset_grant')->where('id',(int)$row['id'])->update($values);else{$values+=['channel_id'=>$channel,'game_id'=>$game,'preset_key'=>$preset,'created_by'=>(int)$actor['id'],'created_at'=>$now];$db->table('partner_control_preset_grant')->insert($values);}$this->audit->record('game_control.grant','success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'control_grant',$channel.':'.$game.':'.$preset,'',$row?:null,$values);}
}
