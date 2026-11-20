<?php

declare(strict_types=1);

namespace app\controller;

use app\service\CsrfTokenService;
use app\service\TenantContext;
use think\Response;

final class Dashboard
{
    public function index(TenantContext $tenant, CsrfTokenService $csrf): Response
    {
        return view('dashboard/index', [
            'identity' => $tenant->user(),
            'logout_token' => $csrf->issue('logout'),
            'activeMenu' => 'dashboard',
        ]);
    }
}
