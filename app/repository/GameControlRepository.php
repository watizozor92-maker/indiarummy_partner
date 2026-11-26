<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class GameControlRepository
{
    public function profiles(?int $channelId): array
    {
        if ($channelId !== null && $channelId > 0) {
            return $this->enrichProfiles($this->inheritedProfiles($channelId));
        }

        $query=Db::connect('game')->table('s_slot_control_profile')->alias('p')
            ->leftJoin('s_game g','g.id=p.game_id')
            ->leftJoin('d_channel_parter c','c.id=p.channel_id')
            ->leftJoin('d_slot_inventory i','i.channel_id=p.channel_id AND i.game_id=p.game_id');
        if($channelId!==null)$query->where('p.channel_id',$channelId);
        return $this->enrichProfiles($query->fieldRaw('p.channel_id,c.agency_uid,c.show_name,p.game_id,g.title,i.currency_code,p.preset_key,p.override_json,p.version,p.parent_version,p.status,p.load_status,p.load_error,p.update_time,i.cash_reserve,i.target_reserve,i.experience_budget,i.pending_liability,i.realized_profit,i.period_bet,i.period_payout,i.period_spins,i.version AS inventory_version,0 AS is_inherited')
            ->order('p.channel_id')->order('p.game_id')->order('i.currency_code')->select()->toArray());
    }

    private function inheritedProfiles(int $channelId): array
    {
        $db = Db::connect('game');
        $merchant = $db->table('d_channel_parter')->where('id', $channelId)->field('agency_uid,show_name')->find() ?: [];
        $defaults = $db->table('s_slot_control_profile')->alias('p')->leftJoin('s_game g', 'g.id=p.game_id')
            ->where('p.channel_id', 0)
            ->field('p.game_id,g.title,p.preset_key,p.override_json,p.version,p.parent_version,p.status,p.load_status,p.load_error,p.update_time')
            ->order('p.game_id')->select()->toArray();
        $overrides = [];
        $overrideVersions = [];
        foreach ($db->table('s_slot_control_profile')->where('channel_id', $channelId)->select()->toArray() as $row) {
            $overrideVersions[(int) $row['game_id']] = (int) $row['version'];
            if ((int) $row['status'] === 1) $overrides[(int) $row['game_id']] = $row;
        }
        $inventories = [];
        foreach ($db->table('d_slot_inventory')->where('channel_id', $channelId)->order('currency_code')->select()->toArray() as $row) {
            $inventories[(int) $row['game_id']][strtoupper((string) $row['currency_code'])] = $row;
        }
        $currencies = array_map('strtoupper', array_map('strval', $db->table('s_channel_currency')
            ->where(['channel_id' => $channelId, 'status' => 1])->order('is_default', 'desc')->order('currency_code')->column('currency_code')));

        $result = [];
        foreach ($defaults as $default) {
            $gameId = (int) $default['game_id'];
            $inherited = !isset($overrides[$gameId]);
            $profile = $overrides[$gameId] ?? $default;
            $dimensions = [];
            if ($currencies !== []) {
                foreach ($currencies as $currency) {
                    $dimensions[] = $inventories[$gameId][$currency] ?? ['currency_code' => $currency];
                }
            } else {
                $dimensions = array_values($inventories[$gameId] ?? [[]]);
            }
            foreach ($dimensions as $inventory) {
                $result[] = [
                    'channel_id' => $channelId,
                    'agency_uid' => $merchant['agency_uid'] ?? '',
                    'show_name' => $merchant['show_name'] ?? '',
                    'game_id' => $gameId,
                    'title' => $default['title'] ?? '',
                    'currency_code' => $inventory['currency_code'] ?? null,
                    'preset_key' => $profile['preset_key'],
                    'override_json' => $profile['override_json'] ?? null,
                    'version' => $inherited ? ($overrideVersions[$gameId] ?? 0) : (int) $profile['version'],
                    'parent_version' => $inherited ? (int) $default['version'] : (int) $profile['parent_version'],
                    'status' => (int) $profile['status'],
                    'load_status' => $inherited ? 'inherited' : $profile['load_status'],
                    'load_error' => $inherited ? '' : $profile['load_error'],
                    'update_time' => $profile['update_time'],
                    'cash_reserve' => $inventory['cash_reserve'] ?? null,
                    'target_reserve' => $inventory['target_reserve'] ?? null,
                    'experience_budget' => $inventory['experience_budget'] ?? null,
                    'pending_liability' => $inventory['pending_liability'] ?? null,
                    'realized_profit' => $inventory['realized_profit'] ?? null,
                    'period_bet' => $inventory['period_bet'] ?? null,
                    'period_payout' => $inventory['period_payout'] ?? null,
                    'period_spins' => $inventory['period_spins'] ?? null,
                    'inventory_version' => $inventory['version'] ?? null,
                    'is_inherited' => $inherited ? 1 : 0,
                ];
            }
        }

        return $result;
    }

    private function enrichProfiles(array $rows): array
    {
        if($rows===[])return [];$db=Db::connect('game');$presets=[];
        foreach($db->table('s_slot_control_preset')->where('status',1)->field('game_id,preset_key,preset_name,settings_json,metrics_json')->select()->toArray() as $preset){$settings=json_decode((string)$preset['settings_json'],true)?:[];$metrics=json_decode((string)$preset['metrics_json'],true)?:[];$stable=$metrics['stable']??reset($metrics)?:[];$presets[(int)$preset['game_id'].':'.(string)$preset['preset_key']]=['name'=>$preset['preset_name'],'targetRtp'=>$settings['targetRtp']??$settings['stableRtp']??null,'stableRtp'=>$stable['rtp']??null,'hitRate'=>$stable['hitRate']??null,'p95'=>$stable['p95']??null,'p99'=>$stable['p99']??null,'maxMultiplier'=>$settings['maxResultMultiplier']??($stable['max']??null),'experienceAssist'=>!empty($settings['experienceAssist']['enabled'])];}
        $currencyProfiles=[];$supportedCurrencies=[];foreach($db->table('s_slot_currency_profile')->where('status',1)->field('game_id,currency_code,bets_json,default_bet_index,paytable_base,multiplier_step,inventory_value_rate')->select()->toArray() as $profile){$profile['currency_code']=strtoupper((string)$profile['currency_code']);$profile['bets']=json_decode((string)$profile['bets_json'],true)?:[];unset($profile['bets_json']);$currencyProfiles[(int)$profile['game_id'].':'.$profile['currency_code']]=$profile;$supportedCurrencies[(int)$profile['game_id']][]=$profile['currency_code'];}
        $stats=[];$channelIds=array_values(array_unique(array_map(static fn(array $r): int=>(int)$r['channel_id'],$rows)));
        if($channelIds!==[]){foreach($db->table('d_slot_inventory_ledger')->whereIn('channel_id',$channelIds)->where('create_time','>=',date('Y-m-d H:i:s',time()-7*86400))->fieldRaw('channel_id,game_id,currency_code,SUM(bet) bet_amount,SUM(payout) payout_amount,COUNT(*) spins,SUM(CASE WHEN payout>0 THEN 1 ELSE 0 END) hits,SUM(CASE WHEN create_time>=DATE_SUB(NOW(),INTERVAL 1 DAY) THEN bet ELSE 0 END) bet_24h,SUM(CASE WHEN create_time>=DATE_SUB(NOW(),INTERVAL 1 DAY) THEN payout ELSE 0 END) payout_24h')->group('channel_id,game_id,currency_code')->select()->toArray() as $stat){$stats[(int)$stat['channel_id'].':'.(int)$stat['game_id'].':'.strtoupper((string)$stat['currency_code'])]=$stat;}}
        foreach($rows as &$row){$preset=$presets[(int)$row['game_id'].':'.(string)$row['preset_key']]??[];$currencyProfile=$currencyProfiles[(int)$row['game_id'].':'.strtoupper((string)($row['currency_code']??''))]??[];$stat=$stats[(int)$row['channel_id'].':'.(int)$row['game_id'].':'.strtoupper((string)($row['currency_code']??''))]??[];$bet7=(float)($stat['bet_amount']??0);$bet24=(float)($stat['bet_24h']??0);$spins=(int)($stat['spins']??0);$controlRequest=json_decode((string)($row['override_json']??''),true)?:[];$row['control_mode']=($controlRequest['mode']??'preset')==='custom'?'custom':'preset';$row['requested_rtp']=isset($controlRequest['requested']['rtp'])?(float)$controlRequest['requested']['rtp']:null;$row['requested_hit_rate']=isset($controlRequest['requested']['hitRate'])?(float)$controlRequest['requested']['hitRate']:null;$row['attainable_rtp']=isset($controlRequest['attainable']['rtp'])?(float)$controlRequest['attainable']['rtp']:null;$row['attainable_hit_rate']=isset($controlRequest['attainable']['hitRate'])?(float)$controlRequest['attainable']['hitRate']:null;$row['control_within_tolerance']=$controlRequest['withinTolerance']??null;unset($row['override_json']);$row['preset_name']=$preset['name']??$row['preset_key'];$row['target_rtp']=$preset['targetRtp']??null;$row['calibrated_rtp']=$preset['stableRtp']??null;$row['target_hit_rate']=$preset['hitRate']??null;$row['p95_multiplier']=$preset['p95']??null;$row['p99_multiplier']=$preset['p99']??null;$row['max_multiplier']=$preset['maxMultiplier']??null;$row['experience_assist']=$preset['experienceAssist']??false;$row['supported_currencies']=array_values(array_unique($supportedCurrencies[(int)$row['game_id']]??[]));$row['bets']=$currencyProfile['bets']??[];$row['default_bet_index']=$currencyProfile['default_bet_index']??null;$row['paytable_base']=$currencyProfile['paytable_base']??null;$row['multiplier_step']=$currencyProfile['multiplier_step']??null;$row['actual_rtp_24h']=$bet24>0?(float)($stat['payout_24h']??0)/$bet24:null;$row['actual_rtp_7d']=$bet7>0?(float)($stat['payout_amount']??0)/$bet7:null;$row['actual_hit_rate_7d']=$spins>0?(int)($stat['hits']??0)/$spins:null;$row['spins_7d']=$spins;}
        return $rows;
    }

    public function presets(int $gameId, ?array $allowedKeys = null): array
    {
        $query=Db::connect('game')->table('s_slot_control_preset')->where('game_id',$gameId)->where('status',1);
        if($allowedKeys!==null)$query->whereIn('preset_key',$allowedKeys!==[]?$allowedKeys:['__NONE__']);
        $rows=$query->field('game_id,preset_key,preset_name,description,settings_json,metrics_json,version,update_time')->order('preset_key')->select()->toArray();
        foreach($rows as &$row){$row['settings']=json_decode((string)$row['settings_json'],true);$row['metrics']=json_decode((string)$row['metrics_json'],true);unset($row['settings_json'],$row['metrics_json']);}
        return $rows;
    }

    public function revisions(int $channelId,int $gameId): array
    {
        $rows=Db::connect('game')->table('d_slot_control_revision')->where('channel_id',$channelId)->where('game_id',$gameId)
            ->field('revision_id,channel_id,game_id,version,parent_version,preset_key,action,validation_json,operator_id,operator_name,operator_ip,create_time')
            ->order('version','desc')->limit(100)->select()->toArray();
        foreach($rows as &$row){$row['validation']=json_decode((string)($row['validation_json']??''),true);unset($row['validation_json']);}
        return $rows;
    }
}
