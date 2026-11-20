<?php

declare(strict_types=1);

namespace app\controller;

use app\service\CsrfTokenService;
use app\service\DateRangeService;
use app\service\TenantContext;
use think\Response;

final class Rounds
{
    public function index(TenantContext $tenant, CsrfTokenService $csrf, DateRangeService $dates): Response
    {
        $range = $dates->parse(null, null, 7, 92);
        return view('rounds/index', [
            'identity' => $tenant->user(),
            'logout_token' => $csrf->issue('logout'),
            'activeMenu' => 'rounds',
            'default_from' => $range['from']->format('Y-m-d'),
            'default_to' => $range['to']->format('Y-m-d'),
        ]);
    }
}
