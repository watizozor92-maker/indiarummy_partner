<?php

declare(strict_types=1);

namespace app\service;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class WhitelistManager
{
    public function __construct(private readonly IpWhitelistService $validator,private readonly AuditService $audit){}
    public function list(int $channelId):array{return Db::connect('partner')->table('partner_admin_ip_whitelist')->where('channel_id',$channelId)->field('id,channel_id,ip_or_cidr,status,remark,created_by,created_at,updated_at')->order('id','desc')->select()->toArray();}
    public function create(int $channelId,string $rule,string $remark,array $actor,string $ip,string $agent):int
    {
        $rule=trim($rule);if(!$this->validator->isValidRule($rule))throw new InvalidArgumentException('IP 或 CIDR 格式无效');
        $now=(new DateTimeImmutable())->format('Y-m-d H:i:s');
        try{$id=(int)Db::connect('partner')->table('partner_admin_ip_whitelist')->insertGetId(['channel_id'=>$channelId,'ip_or_cidr'=>$rule,'status'=>1,'remark'=>mb_substr(trim($remark),0,255),'created_by'=>(int)$actor['id'],'created_at'=>$now,'updated_at'=>$now]);}
        catch(\Throwable $e){throw new InvalidArgumentException('该渠道已存在相同的白名单规则');}
        $this->audit->record('merchant.ip_whitelist.create','success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'ip_whitelist',(string)$id,'',null,['channelId'=>$channelId,'rule'=>$rule,'remark'=>$remark]);
        return $id;
    }
    public function update(int $channelId,int $id,int $status,string $remark,array $actor,string $ip,string $agent):void
    {
        $db=Db::connect('partner');$row=$db->table('partner_admin_ip_whitelist')->where('channel_id',$channelId)->where('id',$id)->find();if(!is_array($row))throw new RuntimeException('白名单规则不存在');
        if($status===0&&(int)$row['status']===1&&$db->table('partner_admin_ip_whitelist')->where('channel_id',$channelId)->where('status',1)->count()<=1)throw new InvalidArgumentException('不能停用最后一条有效白名单规则');
        $after=['status'=>$status===1?1:0,'remark'=>mb_substr(trim($remark),0,255),'updated_at'=>(new DateTimeImmutable())->format('Y-m-d H:i:s')];$db->table('partner_admin_ip_whitelist')->where('id',$id)->update($after);
        $this->audit->record('merchant.ip_whitelist.update','success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'ip_whitelist',(string)$id,'',$row,array_merge($row,$after));
    }
    public function delete(int $channelId,int $id,array $actor,string $ip,string $agent):void
    {
        $db=Db::connect('partner');$row=$db->table('partner_admin_ip_whitelist')->where('channel_id',$channelId)->where('id',$id)->find();if(!is_array($row))throw new RuntimeException('白名单规则不存在');
        if((int)$row['status']===1&&$db->table('partner_admin_ip_whitelist')->where('channel_id',$channelId)->where('status',1)->count()<=1)throw new InvalidArgumentException('不能删除最后一条有效白名单规则');
        $db->table('partner_admin_ip_whitelist')->where('id',$id)->delete();$this->audit->record('merchant.ip_whitelist.delete','success',$ip,$agent,(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'ip_whitelist',(string)$id,'',$row,null);
    }
}
