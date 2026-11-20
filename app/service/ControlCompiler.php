<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use think\facade\Db;

final class ControlCompiler
{
    public function compile(int $channelId,int $gameId,string $presetKey):array
    {
        $db=Db::connect('game');
        $default=$db->table('s_slot_control_profile')->where(['channel_id'=>0,'game_id'=>$gameId])->find();
        $preset=$db->table('s_slot_control_preset')->where(['game_id'=>$gameId,'preset_key'=>$presetKey,'status'=>1])->find();
        if(!is_array($default)||!is_array($preset))throw new InvalidArgumentException('默认配置或控制预设不存在');
        $scenes=$this->decode((string)$default['effective_scenes_json']);
        $settings=$this->decode((string)$preset['settings_json']);
        $policies=$this->decode((string)$preset['policies_json']);
        $defaultPolicies=$scenes['policies']??[];
        $scenes['settings']=$this->merge($scenes['settings']??[],$settings);
        $scenes['policies']=$channelId===0?$policies:$this->copyCampaignControls($policies,$defaultPolicies);
        $this->validate($gameId,$scenes);
        return ['effectiveScenes'=>$scenes,'preset'=>$preset,'metrics'=>$this->decode((string)($preset['metrics_json']??'')),'defaultVersion'=>(int)$default['version']];
    }
    private function decode(string $json):array{$value=json_decode($json,true);return is_array($value)?$value:[];}
    private function merge(array $base,array $override):array{foreach($override as $key=>$value){if(!is_array($value)||!isset($base[$key])||!is_array($base[$key])){$base[$key]=$value;continue;}$isList=$value===[]||array_keys($value)===range(0,count($value)-1);$base[$key]=$isList?$value:$this->merge($base[$key],$value);}return $base;}
    private function copyCampaignControls(array $policies,array $defaults):array{$map=[];foreach($defaults as $p){if(isset($p['name']))$map[$p['name']]=$p;}foreach($policies as &$p){$source=$map[$p['name']??'']??null;if($source&&($p['kind']??'')==='campaign')$p['activation']=$source['activation']??($p['activation']??[]);}unset($p);return $policies;}
    private function validate(int $gameId,array $scenes):void
    {
        if(!isset($scenes['settings'],$scenes['policies'])||!is_array($scenes['policies']))throw new InvalidArgumentException('配置缺少 settings 或 policies');
        $rtp=(float)($scenes['settings']['targetRtp']??0);if($rtp<=0||$rtp>2)throw new InvalidArgumentException('目标 RTP 必须在 0 到 2 之间');
        $names=[];$hasDefault=false;
        foreach($scenes['policies'] as $policy){$name=(string)($policy['name']??'');if($name===''||isset($names[$name]))throw new InvalidArgumentException('场景名称缺失或重复');$names[$name]=true;if(($policy['kind']??'')==='normal'&&!empty($policy['condition']['default']))$hasDefault=true;$groups=[];foreach(($policy['modes']??[]) as $mode){$group=(string)($mode['weightGroup']??'paid');$weight=$mode['weight']??null;if(!is_int($weight)||$weight<0)throw new InvalidArgumentException("场景 {$name} 存在非法玩法权重");$groups[$group]=($groups[$group]??0)+$weight;$rangeTotal=0;foreach(($mode['multiples']??[]) as $range){$rangeWeight=$range['weight']??null;if(!is_int($rangeWeight)||$rangeWeight<0||!is_numeric($range['min']??null))throw new InvalidArgumentException("场景 {$name} 存在非法倍率区间");$rangeTotal+=$rangeWeight;}if($rangeTotal!==10000)throw new InvalidArgumentException("场景 {$name} 的倍率权重合计不是 10000");}foreach($groups as $group=>$total){if($total!==10000)throw new InvalidArgumentException("场景 {$name} 玩法组 {$group} 权重合计不是 10000");}}
        if(!$hasDefault)throw new InvalidArgumentException('缺少默认场景');
        $this->validateCollectionControls($scenes);
        if($gameId===419)$this->validate419($scenes);
    }
    private function validateCollectionControls(array $scenes):void
    {
        $control=$scenes['settings']['collectionControl']??null;
        if(is_array($control)){
            $maximum=(float)($control['maxProbability']??-1);if($maximum<0||$maximum>0.35)throw new InvalidArgumentException('含收集图标的付费 SPIN 概率不能超过 35%');
            $previous=PHP_INT_MAX;foreach(($control['counts']??[]) as $index=>$item){$weight=$item['weight']??null;if(($item['count']??null)!==$index+1||!is_int($weight)||$weight<=0||$weight>=$previous)throw new InvalidArgumentException('收集图标数量权重必须为正整数并严格递减');$previous=$weight;}
            foreach(($control['ranges']??[]) as $range){if((float)($range['min']??-1)!==0.0||(float)($range['max']??-1)!==1.0)throw new InvalidArgumentException('增加收集进度的结果池只能使用真实 0 倍区间 [0,1)');}
            foreach($scenes['policies'] as $policy){$weights=$control['sceneWeights'][$policy['name']??'']??null;if(!is_array($weights))continue;$base=$weights['baseWeight']??null;$collection=$weights['collectionWeight']??null;if(!is_int($base)||!is_int($collection)||$base<0||$collection<0||$base+$collection!==10000||$collection/10000>$maximum)throw new InvalidArgumentException('普通 SPIN 与收集档位必须在同一 10000 权重组内，且收集概率不超过上限');}
        }
        $map=$scenes['settings']['mapCollection']??null;
        if(is_array($map))foreach(($map['sceneWeights']??[]) as $weights){$base=(float)($weights['baseWeight']??-1);$collection=(float)($weights['collectionWeight']??-1);if($base<0||$collection<=0||abs($base+$collection-10000)>0.000001||$collection/10000>0.35)throw new InvalidArgumentException('地图收集与普通 SPIN 权重须合计 10000，收集概率不能超过 35%');}
    }
    private function validate419(array $scenes):void
    {
        $control=$scenes['settings']['collection419']??null;if(!is_array($control))return;$maximum=(float)($control['maxProbability']??-1);if($maximum<0||$maximum>0.35)throw new InvalidArgumentException('419 含收集图标的付费 SPIN 概率不能超过 35%');$counts=$control['counts']??[];if(count($counts)!==5)throw new InvalidArgumentException('419 收集图标数量必须为 1 到 5 个');$previous=PHP_INT_MAX;$sum=0;$expected=[];foreach($counts as $index=>$item){$weight=$item['weight']??null;if(($item['count']??null)!==$index+1||!is_int($weight)||$weight<=0||$weight>=$previous)throw new InvalidArgumentException('419 收集数量权重必须严格递减');$previous=$weight;$sum+=$weight;$expected['collect_c'.($index+1)]=$weight;}if($sum!==3000)throw new InvalidArgumentException('419 收集档位权重合计必须为 3000');foreach($scenes['policies'] as $policy){$paid=0;$withSymbols=0;foreach(($policy['modes']??[]) as $mode){if(($mode['weightGroup']??'paid')!=='paid')continue;$weight=(int)($mode['weight']??0);$paid+=$weight;$main=$mode['source']['main']??'';$sub=$mode['source']['sub']??'';if($main==='normal'&&isset($expected[$sub])&&$weight!==$expected[$sub])throw new InvalidArgumentException('419 收集数量玩法权重与档位配置不一致');if($main==='free_trigger'||$main==='bonus'||($main==='normal'&&isset($expected[$sub])))$withSymbols+=$weight;}if($paid!==10000||$withSymbols/$paid>$maximum)throw new InvalidArgumentException('419 付费玩法权重或收集概率无效');}}
}
