<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\PermissionService;
use Closure;
use think\facade\Session;
use think\Request;
use think\Response;

final class RequirePermission
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $identity = Session::get('partner_auth', []);
        $roleCode = is_array($identity) ? (string) ($identity['role_code'] ?? '') : '';

        if (!$this->permissions->allows($roleCode, $permission)) {
            if ($request->isAjax() || str_starts_with($request->pathinfo(), 'api/')) {
                return json(['code' => 'FORBIDDEN', 'message' => '没有操作权限', 'data' => null], 403);
            }

            return response('没有操作权限', 403);
        }

        return $next($request);
    }
}
