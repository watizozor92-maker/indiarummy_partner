<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\MerchantRepository;
use app\service\ApiResponder;
use app\service\CsrfTokenService;
use app\service\MerchantCurrencyService;
use app\service\MerchantService;
use app\service\TenantContext;
use InvalidArgumentException;
use RuntimeException;
use think\Request;
use think\Response;

final class Merchants
{
    public function create(Request $request, TenantContext $tenant, CsrfTokenService $csrf, MerchantService $service, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) {
            return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        }
        try {
            $result = $service->create($request->post(), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $result['csrfToken'] = $csrf->issue('api');
            return $api->success($result, '商户已创建', 201);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'PLATFORM_ADMIN_REQUIRED') return $api->error('FORBIDDEN', '仅平台管理员可以创建商户', 403);
            if ($exception->getMessage() === 'MERCHANT_CODE_CONFLICT') return $api->error('MERCHANT_CODE_CONFLICT', '商户编码已存在', 409);
            if (str_starts_with($exception->getMessage(), 'MERCHANT_ADMIN_CREATE')) {
                return $api->error('MERCHANT_ADMIN_CREATE_FAILED', '商户后台账号创建失败，商户创建已回滚', 500);
            }
            return $api->error('CREATE_FAILED', '商户创建失败', 500);
        }
    }

    public function index(Request $request, MerchantRepository $repository, ApiResponder $api): Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
        $statusValue = $request->get('status');
        $status = $statusValue === null || $statusValue === '' ? null : (int) $statusValue;

        return $api->success($repository->paginate(trim((string) $request->get('keyword', '')), $status, $page, $pageSize));
    }

    public function update(int $channelId, Request $request, TenantContext $tenant, CsrfTokenService $csrf, MerchantService $service, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) {
            return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        }
        try {
            $result = $service->update($channelId, $request->post(), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $result['csrfToken'] = $csrf->issue('api');
            return $result['runtimeStatus'] === 'failed'
                ? $api->error('RUNTIME_LOAD_FAILED', '商户信息已保存，但游戏服务热加载失败', 502, $result)
                : $api->success($result, $result['runtimeStatus'] === 'loaded' ? '商户信息已更新并加载' : '商户信息已更新，将在游戏服务启动后生效');
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'PLATFORM_ADMIN_REQUIRED') return $api->error('FORBIDDEN', '仅平台管理员可以修改商户', 403);
            if ($exception->getMessage() === 'MERCHANT_NOT_FOUND') return $api->error('RESOURCE_NOT_FOUND', '商户不存在', 404);
            return $api->error('UPDATE_FAILED', '商户信息更新失败', 500);
        }
    }

    public function detail(int $channelId, MerchantRepository $repository, ApiResponder $api): Response
    {
        $merchant = $repository->find($channelId);

        return $merchant === null
            ? $api->error('RESOURCE_NOT_FOUND', '商户不存在', 404)
            : $api->success($merchant);
    }

    public function options(MerchantRepository $repository, ApiResponder $api): Response
    {
        return $api->success($repository->options());
    }

    public function currencyOptions(MerchantCurrencyService $currencies, ApiResponder $api): Response
    {
        return $api->success($currencies->options());
    }

    public function updateCurrencies(int $channelId, Request $request, TenantContext $tenant, CsrfTokenService $csrf, MerchantCurrencyService $currencies, ApiResponder $api): Response
    {
        if (!$csrf->validate('api', (string) $request->header('x-csrf-token', ''))) {
            return $api->error('CSRF_INVALID', '页面已过期，请刷新后重试', 419);
        }
        $codes = $request->post('currencyCodes', []);
        if (is_string($codes)) {
            $codes = array_filter(array_map('trim', explode(',', $codes)));
        }
        try {
            $result = $currencies->replace($channelId, is_array($codes) ? $codes : [], (string) $request->post('defaultCurrency', ''), $tenant->user(), (string) $request->ip(), (string) $request->header('user-agent', ''));
            $result['csrfToken'] = $csrf->issue('api');
            return $result['runtimeStatus'] === 'failed'
                ? $api->error('RUNTIME_LOAD_FAILED', '币种配置已保存，但运行端热加载失败', 502, $result)
                : $api->success($result, $result['runtimeStatus'] === 'loaded' ? '币种配置已更新并加载' : '币种配置已更新，将在游戏服务启动后生效');
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $api->error('RESOURCE_NOT_FOUND', $exception->getMessage(), 404);
        }
    }
}
