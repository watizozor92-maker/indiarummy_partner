<?php

declare(strict_types=1);

namespace app\controller;

use app\service\CsrfTokenService;
use app\service\TenantContext;
use think\Response;

final class Merchants
{
    public function index(TenantContext $tenant, CsrfTokenService $csrf): Response
    {
        return view('merchants/index', ['identity' => $tenant->user(), 'logout_token' => $csrf->issue('logout'), 'activeMenu' => 'merchants']);
    }
}
