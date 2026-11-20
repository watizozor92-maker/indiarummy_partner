<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class AuditRepository
{
    public function paginate(?int $channelId,int $actorId,string $action,int $page,int $pageSize):array
    {
        $query=Db::connect('partner')->table('partner_audit_log');
        if($channelId!==null)$query->where('channel_id',$channelId);
        if($actorId>0)$query->where('actor_id',$actorId);
        if($action!=='')$query->whereLike('action',$action.'%');
        $total=(clone $query)->count();
        $items=$query->field('id,request_id,actor_id,channel_id,role_code,action,resource_type,resource_id,result,reason,ip_address,user_agent,created_at')
            ->order('id','desc')->page($page,$pageSize)->select()->toArray();
        return ['items'=>$items,'page'=>$page,'pageSize'=>$pageSize,'total'=>$total];
    }
}
