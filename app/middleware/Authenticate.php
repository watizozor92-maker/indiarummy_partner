<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\facade\Session;
use think\Request;
use think\Response;

final class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $identity = Session::get('partner_auth');
        if (!is_array($identity) || empty($identity['id'])) {
            if ($request->isAjax() || str_starts_with($request->pathinfo(), 'api/')) {
                return json(['code' => 'AUTH_REQUIRED', 'message' => '请先登录', 'data' => null], 401);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
