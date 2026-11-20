<?php
declare(strict_types=1);
namespace app\controller\api;
use app\service\ApiResponder;use app\service\CsrfTokenService;use app\service\TenantContext;use think\Response;
final class Session{public function index(TenantContext $tenant,CsrfTokenService $csrf,ApiResponder $api):Response{return $api->success(['identity'=>$tenant->user(),'csrfToken'=>$csrf->issue('api')]);}}
