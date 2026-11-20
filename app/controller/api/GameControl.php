<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\GameControlRepository;
use app\service\ApiResponder;
use app\service\ControlGrantService;
use app\service\ControlWorkflowService;
use app\service\CsrfTokenService;
use app\service\InternalGameApiClient;
use app\service\TenantContext;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\Response;

final class GameControl
{
    public function index(Request $request,TenantContext $tenant,GameControlRepository $repository,ApiResponder $api):Response
    {
        $rawChannel=$request->get('channelId');$channel=$tenant->resolveChannelId($rawChannel===null||$rawChannel===''?null:(int)$rawChannel);
        $rows=$repository->profiles($channel);
        foreach($rows as &$row){foreach(['cash_reserve','target_reserve','experience_budget','pending_liability','realized_profit','period_bet','period_payout','period_spins','inventory_version'] as $field)unset($row[$field]);}
        return $api->success($rows);
    }
    public function presets(int $gameId,GameControlRepository $repository,ApiResponder $api):Response{return $api->success($repository->presets($gameId));}
    public function revisions(int $gameId,Request $request,TenantContext $tenant,GameControlRepository $repository,ApiResponder $api):Response
    {
        $rawChannel=$request->get('channelId');$channel=$tenant->resolveChannelId($rawChannel===null||$rawChannel===''?null:(int)$rawChannel);
        if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);
        return $api->success($repository->revisions($channel,$gameId));
    }
    public function runtimeStatus(int $gameId,Request $request,TenantContext $tenant,InternalGameApiClient $runtime,ApiResponder $api):Response
    { $raw=$request->get('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{return $api->success($runtime->status($channel,$gameId,(string)$request->get('currencyCode','')));}catch(RuntimeException $e){return $api->error('RUNTIME_LOAD_FAILED',$e->getMessage(),502);} }
    public function preview(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$workflow->preview($tenant->user(),$channel,$gameId,(string)$request->post('currencyCode',''),trim((string)$request->post('presetKey','')),(int)$request->post('expectedVersion',0),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $api->success($data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=$e->getMessage()==='CONTROL_VERSION_CONFLICT'?'CONTROL_VERSION_CONFLICT':($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$code==='RUNTIME_NOT_CONFIGURED'?'内部游戏管理 API 尚未配置':$e->getMessage(),$code==='CONTROL_VERSION_CONFLICT'?409:503);}}
    public function publish(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);try{$data=$workflow->publish($tenant->user(),$gameId,(string)$request->post('previewToken',''),(string)$request->post('idempotencyKey',''),(string)$request->post('reason',''),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $data['loadStatus']==='loaded'?$api->success($data):$api->error('RUNTIME_LOAD_FAILED','配置已保存，但运行端加载失败',502,$data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=in_array($e->getMessage(),['PREVIEW_EXPIRED','CONTROL_VERSION_CONFLICT','IDEMPOTENCY_CONFLICT','REQUEST_IN_PROGRESS'],true)?$e->getMessage():'RUNTIME_LOAD_FAILED';return $api->error($code,$e->getMessage(),$code==='RUNTIME_LOAD_FAILED'?502:409);}}
    public function rollback(int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlWorkflowService $workflow,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);$raw=$request->post('channelId');$channel=$tenant->resolveChannelId($raw===null||$raw===''?null:(int)$raw);if($channel===null)return $api->error('VALIDATION_FAILED','请选择商户',422);try{$data=$workflow->rollback($tenant->user(),$channel,$gameId,(int)$request->post('targetVersion',0),(int)$request->post('expectedVersion',0),(string)$request->post('idempotencyKey',''),(string)$request->post('reason',''),(string)$request->ip(),(string)$request->header('user-agent',''));$data['csrfToken']=$csrf->issue('api');return $data['loadStatus']==='loaded'?$api->success($data):$api->error('RUNTIME_LOAD_FAILED','回滚版本已保存，但运行端加载失败',502,$data);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}catch(RuntimeException $e){$code=in_array($e->getMessage(),['CONTROL_VERSION_CONFLICT','IDEMPOTENCY_CONFLICT','REQUEST_IN_PROGRESS'],true)?$e->getMessage():($e->getMessage()==='RUNTIME_NOT_CONFIGURED'?'RUNTIME_NOT_CONFIGURED':'RUNTIME_LOAD_FAILED');return $api->error($code,$e->getMessage(),$code==='RUNTIME_LOAD_FAILED'?502:409);}}
    public function grants(int $channelId,ControlGrantService $grants,ApiResponder $api):Response{return $api->success($grants->list($channelId));}
    public function setGrant(int $channelId,int $gameId,Request $request,TenantContext $tenant,CsrfTokenService $csrf,ControlGrantService $grants,ApiResponder $api):Response
    {if(!$csrf->validate('api',(string)$request->header('x-csrf-token','')))return $api->error('CSRF_INVALID','页面已过期，请刷新后重试',419);try{$grants->set($channelId,$gameId,trim((string)$request->post('presetKey','')),(int)$request->post('status',1),$tenant->user(),(string)$request->ip(),(string)$request->header('user-agent',''));return $api->success(['csrfToken'=>$csrf->issue('api')],'授权已更新');}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}}
}
