<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\GameControlRepository;
use app\service\ApiResponder;
use app\service\ControlGrantService;
use app\service\ControlWorkflowService;
use app\service\CsrfTokenService;
use app\service\InternalGameApiClient;
use app\service\TargetControlService;
use app\service\TenantContext;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\Response;

final class GameControl
{
    public function index(Request $request,TenantContext $tenant,GameControlRepository $repository,ControlGrantService $grants,ApiResponder $api):Response
    {
        $rawChannel=$request->get('channelId');$channel=$tenant->resolveChannelId($rawChannel===null||$rawChannel===''?null:(int)$rawChannel);if($tenant->isPlatformAdmin()&&($rawChannel===null||$rawChannel===''))$channel=0;
        $rows=$repository->profiles($channel);
        $scopes=[];foreach($rows as &$row){$gameId=(int)$row['game_id'];if($channel!==null&&$channel>0)$row['control_scope']=$scopes[$gameId]??=$grants->scope($channel,$gameId);foreach(['cash_reserve','target_reserve','experience_budget','pending_liability','realized_profit','period_bet','period_payout','period_spins','inventory_version'] as $field)unset($row[$field]);}
        if((string)$tenant->user()['role_code']!=='platform_admin')foreach($rows as &$row){foreach(['agency_uid','show_name','parent_version','load_error','calibrated_rtp','p95_multiplier','p99_multiplier','max_multiplier','experience_assist','bets','default_bet_index','paytable_base','multiplier_step'] as $field)unset($row[$field]);}
        return $api->success($rows);
    }
    public function presets(int $gameId,TenantContext $tenant,ControlGrantService $grants,GameControlRepository $repository,ApiResponder $api):Response
    {
        $actor=$tenant->user();$keys=(string)$actor['role_code']==='platform_admin'?null:$grants->allowedPresetKeys($actor,(int)$actor['channel_id'],$gameId);$rows=$repository->presets($gameId,$keys);
        if((string)$actor['role_code']!=='platform_admin')foreach($rows as &$row){$settings=$row['settings']??[];$metrics=$row['metrics']??[];$stable=$metrics['stable']??reset($metrics)?:[];$row['target_rtp']=$settings['targetRtp']??$settings['stableRtp']??null;$row['hit_rate']=$stable['hitRate']??null;unset($row['settings'],$row['metrics'],$row['version'],$row['update_time']);}
        return $api->success($rows);
    }
    public function scope(int $gameId,Request $request,TenantContext $tenant,ControlGrantService $grants,ApiResponder $api):Response
    {$raw=$request->get('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);return $api->success($grants->scope($channel,$gameId));}
    public function targetPreview(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,TargetControlService $targets,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$targets->preview($tenant->user(),$channel,$gameId,(string)$request->post('currencyCode',''),(float)$request->post('targetRtp',0),(float)$request->post('targetHitRate',0),(int)$request->post('expectedVersion',0),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $api->success($data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=$e->getMessage()==='CONTROL_VERSION_CONFLICT'?'CONTROL_VERSION_CONFLICT':($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$code==='RUNTIME_NOT_CONFIGURED'?'内部游戏管理 API 尚未配置':$e->getMessage(),$code==='CONTROL_VERSION_CONFLICT'?409:503);}}
    public function revisions(int $gameId,Request $request,TenantContext $tenant,GameControlRepository $repository,ApiResponder $api):Response
    {
        $rawChannel=$request->get('channelId');$channel=$tenant->resolveChannelId($rawChannel===null||$rawChannel===''?null:(int)$rawChannel);
        if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);
        $rows=$repository->revisions($channel,$gameId);if((string)$tenant->user()['role_code']!=='platform_admin')foreach($rows as &$row){foreach(['parent_version','validation','operator_id','operator_ip'] as $field)unset($row[$field]);}return $api->success($rows);
    }
    public function runtimeStatus(int $gameId,Request $request,TenantContext $tenant,InternalGameApiClient $runtime,ApiResponder $api):Response
    { $raw=$request->get('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{return $api->success($runtime->status($channel,$gameId,(string)$request->get('currencyCode','')));}catch(RuntimeException $e){return $api->error('RUNTIME_LOAD_FAILED',$e->getMessage(),502);} }
    public function preview(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$workflow->preview($tenant->user(),$channel,$gameId,(string)$request->post('currencyCode',''),trim((string)$request->post('presetKey','')),(int)$request->post('expectedVersion',0),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $api->success($data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=$e->getMessage()==='CONTROL_VERSION_CONFLICT'?'CONTROL_VERSION_CONFLICT':($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$code==='RUNTIME_NOT_CONFIGURED'?'内部游戏管理 API 尚未配置':$e->getMessage(),$code==='CONTROL_VERSION_CONFLICT'?409:503);}}
    public function publish(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);try{$data=$workflow->publish($tenant->user(),$gameId,(string)$request->post('previewToken',''),(string)$request->post('idempotencyKey',''),(string)$request->post('reason',''),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $data['loadStatus']==='loaded'?$api->success($data):$api->error('RUNTIME_LOAD_FAILED','配置已保存，但运行端加载失败',502,$data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=in_array($e->getMessage(),['PREVIEW_EXPIRED','CONTROL_VERSION_CONFLICT','IDEMPOTENCY_CONFLICT','REQUEST_IN_PROGRESS'],true)?$e->getMessage():'RUNTIME_LOAD_FAILED';return $api->error($code,$e->getMessage(),$code==='RUNTIME_LOAD_FAILED'?502:409);}}
    public function rollback(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$workflow->rollback($tenant->user(),$channel,$gameId,(int)$request->post('targetVersion',0),(int)$request->post('expectedVersion',0),(string)$request->post('idempotencyKey',''),(string)$request->post('reason',''),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $data['loadStatus']==='loaded'?$api->success($data):$api->error('RUNTIME_LOAD_FAILED','回滚版本已保存，但运行端加载失败',502,$data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=in_array($e->getMessage(),['CONTROL_VERSION_CONFLICT','IDEMPOTENCY_CONFLICT','REQUEST_IN_PROGRESS'],true)?$e->getMessage():($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$e->getMessage(),$code==='RUNTIME_LOAD_FAILED'?502:409);}}
    public function restoreDefault(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$workflow->restoreDefault($tenant->user(),$channel,$gameId,(int)$request->post('expectedVersion',0),(string)$request->post('idempotencyKey',''),(string)$request->post('reason',''),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $data['loadStatus']==='loaded'?$api->success($data):$api->error('RUNTIME_LOAD_FAILED','已恢复平台默认，但运行端加载失败',502,$data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=in_array($e->getMessage(),['CONTROL_VERSION_CONFLICT','IDEMPOTENCY_CONFLICT','REQUEST_IN_PROGRESS'],true)?$e->getMessage():($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$e->getMessage(),$code==='RUNTIME_LOAD_FAILED'?502:409);}}
    public function grants(int $channelId,ControlGrantService $grants,ApiResponder $api):Response{return $api->success($grants->list($channelId));}
    public function setGrant(int $channelId,int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlGrantService $grants,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);try{$grants->set($channelId,$gameId,trim((string)$request->post('presetKey','')),(int)$request->post('status',1),$tenant->user(),(string)$request->ip(),(string)$request->header('user-agent',''));return $api->success(['csrfToken'=>$csrf->issue('api')],'授权已更新');}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}}
    public function setScope(int $channelId,int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlGrantService $grants,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);try{$data=$grants->saveScope($channelId,$gameId,$request->post(),$tenant->user(),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $api->success($data,'调控范围已更新');}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}}
}
