<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\AuditRepository;
use app\service\ApiResponder;
use app\service\TenantContext;
use think\Request;
use think\Response;

final class Audit
{
    public function index(Request $request,TenantContext $tenant,AuditRepository $repository,ApiResponder $api):Response
    {
        $channel=$tenant->isPlatformAdmin()?($request->get('channelId')?(int)$request->get('channelId'):null):$tenant->channelId();
        $actor=$tenant->isPlatformAdmin()?(int)$request->get('actorId',0):(int)$tenant->user()['id'];
        return $api->success($repository->paginate($channel,$actor,trim((string)$request->get('action','')),max(1,(int)$request->get('page',1)),min(100,max(1,(int)$request->get('pageSize',20)))));
    }
}
