<?php

declare(strict_types=1);

namespace app\controller;

use app\service\CsrfTokenService;
use app\service\DateRangeService;
use app\service\InventoryGrantService;
use app\service\TenantContext;
use think\Response;

final class Inventory
{
    public function overview(TenantContext $tenant, CsrfTokenService $csrf, InventoryGrantService $grants): Response
    {
        return $this->page('inventory/overview', 'inventory_overview', $tenant, $csrf, $grants, 'view');
    }

    public function ledger(TenantContext $tenant, CsrfTokenService $csrf, InventoryGrantService $grants, DateRangeService $dates): Response
    {
        $range = $dates->parse(null, null, 7, 92);
        return $this->page('inventory/ledger', 'inventory_ledger', $tenant, $csrf, $grants, 'ledger', [
            'default_from' => $range['from']->format('Y-m-d'),
            'default_to' => $range['to']->format('Y-m-d'),
        ]);
    }

    public function adjustments(TenantContext $tenant, CsrfTokenService $csrf, InventoryGrantService $grants): Response
    {
        return $this->page('inventory/adjustments', 'inventory_adjustments', $tenant, $csrf, $grants, 'adjust');
    }

    public function grants(TenantContext $tenant, CsrfTokenService $csrf, InventoryGrantService $grants): Response
    {
        return $this->page('inventory/grants', 'inventory_grants', $tenant, $csrf, $grants, 'platform');
    }

    private function page(string $template, string $activeMenu, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryGrantService $grants, string $required, array $extra = []): Response
    {
        $actor = $tenant->user();
        $platform = $tenant->isPlatformAdmin();
        $access = $platform ? $grants->effectiveFor($actor, 0) : $grants->effectiveFor($actor, (int) $actor['channel_id']);
        $allowed = match ($required) {
            'platform' => $platform,
            'ledger' => !empty($access['canViewLedger']),
            'adjust' => !empty($access['canAdjust']),
            default => !empty($access['canView']),
        };
        if (!$allowed) return response('无权访问库存管理功能', 403);

        return view($template, $extra + [
            'identity' => $actor, 'logout_token' => $csrf->issue('logout'),
            'activeMenu' => $activeMenu, 'inventory_access' => $access,
        ]);
    }
}
