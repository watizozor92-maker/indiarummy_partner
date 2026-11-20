<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\InventoryRepository;
use app\service\ApiResponder;
use app\service\CsrfTokenService;
use app\service\DateRangeService;
use app\service\InventoryAdjustmentService;
use app\service\InventoryGrantService;
use app\service\TenantContext;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\Response;

final class Inventory
{
    public function access(TenantContext $tenant, InventoryGrantService $grants, ApiResponder $api): Response
    {
        $actor = $tenant->user();
        return $api->success($grants->effectiveFor($actor, $tenant->isPlatformAdmin() ? 0 : (int) $actor['channel_id']));
    }

    public function overview(Request $request, TenantContext $tenant, InventoryGrantService $grants,
        InventoryRepository $repository, ApiResponder $api): Response
    {
        $actor = $tenant->user();
        $raw = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($raw === null || $raw === '' ? null : (int) $raw);
        $gameIds = $currencyCodes = [];
        if (!$tenant->isPlatformAdmin()) {
            $access = $grants->effectiveFor($actor, (int) $channelId);
            if (empty($access['canView'])) return $api->error('FORBIDDEN', '平台尚未授权查看库存', 403);
            $gameIds = $access['gameIds']; $currencyCodes = $access['currencyCodes'];
        }
        $filterGame = (int) $request->get('gameId', 0);
        $filterCurrency = strtoupper(trim((string) $request->get('currencyCode', '')));
        if ($filterGame > 0) $gameIds = $gameIds === [] || in_array($filterGame, $gameIds, true) ? [$filterGame] : [-1];
        if ($filterCurrency !== '') $currencyCodes = $currencyCodes === [] || in_array($filterCurrency, $currencyCodes, true) ? [$filterCurrency] : ['__DENIED__'];
        $rows = $repository->overview($channelId, $gameIds, $currencyCodes);
        foreach ($rows as &$row) {
            $rowAccess = $grants->effectiveFor($actor, (int) $row['channel_id'], (int) $row['game_id'], (string) $row['currency_code']);
            $row['inventory_access'] = [
                'visibility' => $rowAccess['visibility'], 'canViewLedger' => $rowAccess['canViewLedger'],
                'adjustMode' => $rowAccess['adjustMode'], 'canAdjust' => $rowAccess['canAdjust'],
                'singleLimit' => $rowAccess['singleLimit'], 'dailyLimit' => $rowAccess['dailyLimit'],
            ];
            if (!$tenant->isPlatformAdmin() && $rowAccess['visibility'] !== 'full') {
                foreach (['experience_budget','assist_reserved','campaign_available','campaign_reserved','realized_profit','period_bet','period_payout','period_spins','period_started_at'] as $field) $row[$field] = null;
            }
        }
        return $api->success($rows);
    }

    public function ledger(Request $request, TenantContext $tenant, InventoryGrantService $grants,
        InventoryRepository $repository, DateRangeService $dates, ApiResponder $api): Response
    {
        try {$range = $dates->parse($request->get('from'), $request->get('to'), 7, 92);}
        catch (InvalidArgumentException $e) {return $api->error('VALIDATION_FAILED', $e->getMessage(), 422);}
        $actor = $tenant->user();
        $raw = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($raw === null || $raw === '' ? null : (int) $raw);
        $gameIds = $currencyCodes = [];
        if (!$tenant->isPlatformAdmin()) {
            $access = $grants->effectiveFor($actor, (int) $channelId);
            if (empty($access['canViewLedger'])) return $api->error('FORBIDDEN', '平台尚未授权查看库存流水', 403);
            $gameIds = $access['gameIds']; $currencyCodes = $access['currencyCodes'];
        }
        $filters = [
            'from' => $range['fromSql'], 'to' => $range['toSql'], 'gameId' => $request->get('gameId'),
            'currencyCode' => trim((string) $request->get('currencyCode', '')),
            'eventType' => trim((string) $request->get('eventType', '')), 'uid' => $request->get('uid'),
        ];
        return $api->success($repository->ledger($channelId, $filters, max(1, (int) $request->get('page', 1)),
            min(100, max(1, (int) $request->get('pageSize', 20))), $gameIds, $currencyCodes));
    }

    public function grant(int $channelId, TenantContext $tenant, InventoryGrantService $grants, ApiResponder $api): Response
    {
        if (!$tenant->isPlatformAdmin()) return $api->error('FORBIDDEN', '仅平台管理员可以管理库存授权', 403);
        return $api->success($grants->get($channelId));
    }

    public function saveGrant(int $channelId, Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryGrantService $grants, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        try {
            $data = $grants->save($channelId, $request->post(), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $data['csrfToken'] = $csrf->issue('api');
            return $api->success($data, '库存授权已更新');
        } catch (InvalidArgumentException $e) {return $api->error('VALIDATION_FAILED', $e->getMessage(), 422);}
        catch (RuntimeException $e) {return $api->error($e->getMessage() === 'PLATFORM_ADMIN_REQUIRED' ? 'FORBIDDEN' : 'RESOURCE_NOT_FOUND', $e->getMessage(), $e->getMessage() === 'PLATFORM_ADMIN_REQUIRED' ? 403 : 404);}
    }

    public function adjustments(Request $request, TenantContext $tenant, InventoryGrantService $grants,
        InventoryAdjustmentService $service, ApiResponder $api): Response
    {
        $actor = $tenant->user();
        if (!$tenant->isPlatformAdmin()) {
            $access = $grants->effectiveFor($actor, (int) $actor['channel_id']);
            if (empty($access['canAdjust'])) return $api->error('FORBIDDEN', '平台尚未授权库存调整', 403);
        }
        return $api->success($service->paginate($actor, $request->get('status') === null ? null : (string) $request->get('status'),
            max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20)))));
    }

    public function createAdjustment(Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryAdjustmentService $service, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        try {
            $data = $service->create($request->post(), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $data['csrfToken'] = $csrf->issue('api');
            if ($data['status'] === 'failed') return $api->error('RUNTIME_LOAD_FAILED', '库存调整执行失败', 502, $data);
            return $api->success($data, $data['status'] === 'executed' ? '库存调整已执行' : '库存调整申请已提交', 201);
        } catch (InvalidArgumentException $e) {return $api->error('VALIDATION_FAILED', $e->getMessage(), 422);}
        catch (RuntimeException $e) {return $api->error($e->getMessage() === 'INVENTORY_ADJUST_FORBIDDEN' ? 'FORBIDDEN' : 'ADJUSTMENT_FAILED', $e->getMessage(), $e->getMessage() === 'INVENTORY_ADJUST_FORBIDDEN' ? 403 : 409);}
    }

    public function approve(int $id, Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryAdjustmentService $service, ApiResponder $api): Response
    {
        return $this->review($id, true, $request, $tenant, $csrf, $service, $api);
    }

    public function reject(int $id, Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryAdjustmentService $service, ApiResponder $api): Response
    {
        return $this->review($id, false, $request, $tenant, $csrf, $service, $api);
    }

    private function review(int $id, bool $approve, Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        InventoryAdjustmentService $service, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        try {
            $data = $approve
                ? $service->approve($id, (string) $request->post('reviewReason', ''), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''))
                : $service->reject($id, (string) $request->post('reviewReason', ''), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $data['csrfToken'] = $csrf->issue('api');
            if ($data['status'] === 'failed') return $api->error('RUNTIME_LOAD_FAILED', '审批已记录，但库存调整执行失败', 502, $data);
            return $api->success($data, $approve ? '审批完成' : '申请已拒绝');
        } catch (InvalidArgumentException $e) {return $api->error('VALIDATION_FAILED', $e->getMessage(), 422);}
        catch (RuntimeException $e) {return $api->error($e->getMessage() === 'PLATFORM_ADMIN_REQUIRED' ? 'FORBIDDEN' : 'STATE_CONFLICT', $e->getMessage(), $e->getMessage() === 'PLATFORM_ADMIN_REQUIRED' ? 403 : 409);}
    }
}
