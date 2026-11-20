<?php
declare(strict_types=1);
namespace app\controller;
use app\service\CsrfTokenService;use app\service\TenantContext;use think\Response;
final class PlayerControl{public function index(TenantContext $tenant,CsrfTokenService $csrf):Response{return view('player_control/index',['identity'=>$tenant->user(),'logout_token'=>$csrf->issue('logout'),'activeMenu'=>'player_control']);}}
