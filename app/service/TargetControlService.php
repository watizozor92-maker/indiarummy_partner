<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use think\facade\Db;

final class TargetControlService
{
    public function __construct(private readonly ControlGrantService $grants, private readonly ControlWorkflowService $workflow){}

    public function preview(array $actor,int $channelId,int $gameId,string $currency,float $targetRtpPercent,float $targetHitPercent,int $expectedVersion,string $ip,string $agent):array
    {
        $targetRtp=$targetRtpPercent/100;
        $targetHit=$targetHitPercent/100;
        $scope=$this->grants->scope($channelId,$gameId);
        if((int)$scope['status']!==1||((string)$actor['role_code']!=='platform_admin'&&(int)$scope['allow_custom']!==1))throw new InvalidArgumentException('该商户未获授权使用自定义目标');
        if($targetRtp<(float)$scope['rtp_min']||$targetRtp>(float)$scope['rtp_max'])throw new InvalidArgumentException('目标 RTP 超出授权范围');
        if($targetHit<(float)$scope['hit_min']||$targetHit>(float)$scope['hit_max'])throw new InvalidArgumentException('目标中奖率超出授权范围');
        if(!$this->aligned($targetRtp,(float)$scope['rtp_min'],(float)$scope['rtp_step']))throw new InvalidArgumentException('目标 RTP 不符合授权步长');
        if(!$this->aligned($targetHit,(float)$scope['hit_min'],(float)$scope['hit_step']))throw new InvalidArgumentException('目标中奖率不符合授权步长');
        $keys=$this->grants->allowedPresetKeys($actor,$channelId,$gameId);if($keys===[])throw new InvalidArgumentException('该游戏没有已授权的可用方案');
        $rows=Db::connect('game')->table('s_slot_control_preset')->where(['game_id'=>$gameId,'status'=>1])->whereIn('preset_key',$keys)->select()->toArray();
        $best=null;$bestScore=INF;
        foreach($rows as $row){$settings=json_decode((string)$row['settings_json'],true)?:[];$metrics=json_decode((string)($row['metrics_json']??''),true)?:[];$stable=$metrics['stable']??reset($metrics);if(!is_array($stable))continue;$rtp=(float)($settings['targetRtp']??$settings['stableRtp']??$stable['rtp']??0);$hit=(float)($stable['hitRate']??0);if($rtp<=0||$hit<=0)continue;$score=abs($rtp-$targetRtp)/max((float)$scope['rtp_step'],0.0001)+abs($hit-$targetHit)/max((float)$scope['hit_step'],0.0001);if($score<$bestScore){$bestScore=$score;$best=['row'=>$row,'rtp'=>$rtp,'hit'=>$hit];}}
        if($best===null)throw new InvalidArgumentException('已授权方案缺少有效的 RTP 或中奖率校准数据');
        $preview=$this->workflow->preview($actor,$channelId,$gameId,$currency,(string)$best['row']['preset_key'],$expectedVersion,$ip,$agent);
        $rtpDeviation=(float)$best['rtp']-$targetRtp;$hitDeviation=(float)$best['hit']-$targetHit;
        $requested=['rtp'=>$targetRtp,'hitRate'=>$targetHit];$attainable=['rtp'=>(float)$best['rtp'],'hitRate'=>(float)$best['hit'],'presetKey'=>(string)$best['row']['preset_key'],'presetName'=>(string)$best['row']['preset_name']];$deviation=['rtp'=>$rtpDeviation,'hitRate'=>$hitDeviation];$withinTolerance=abs($rtpDeviation)<=max((float)$scope['rtp_step'],0.001)&&abs($hitDeviation)<=max((float)$scope['hit_step'],0.005);
        $this->workflow->annotatePreview($actor,(string)$preview['previewToken'],['mode'=>'custom','requested'=>$requested,'attainable'=>$attainable,'deviation'=>$deviation,'withinTolerance'=>$withinTolerance]);
        return $preview+['requested'=>$requested,'attainable'=>$attainable,'deviation'=>$deviation,'withinTolerance'=>$withinTolerance,'scope'=>$scope];
    }
    private function aligned(float $value,float $minimum,float $step):bool{return $step>0&&abs((($value-$minimum)/$step)-round(($value-$minimum)/$step))<0.00001;}
}
