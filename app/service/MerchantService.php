<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class MerchantService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly InternalGameApiClient $runtime,
    ) {
    }

    public function create(array $input, array $actor, string $ip, string $agent): array
    {
        if ((string) ($actor['role_code'] ?? '') !== 'platform_admin' || (int) ($actor['channel_id'] ?? -1) !== 0) {
            throw new RuntimeException('PLATFORM_ADMIN_REQUIRED');
        }

        $agencyUid = trim((string) ($input['agencyUid'] ?? ''));
        $showName = trim((string) ($input['showName'] ?? ''));
        $backRateText = trim((string) ($input['backRate'] ?? '0'));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $backRateText)) throw new InvalidArgumentException('返点必须为最多两位小数的数字');
        $backRate = round((float) $backRateText, 2);
        $phone = trim((string) ($input['phone'] ?? ''));
        $socialCode = trim((string) ($input['socialCode'] ?? ''));
        $creditLimit = $this->nonNegativeInteger($input['creditLimit'] ?? 0, '信用额度');
        $purchaseLimit = $this->nonNegativeInteger($input['purchaseLimit'] ?? 0, '采购额度');
        $status = (int) ($input['status'] ?? 1) === 1 ? 1 : 0;
        $codes = $input['currencyCodes'] ?? [];
        if (is_string($codes)) $codes = array_filter(array_map('trim', explode(',', $codes)));
        $codes = array_values(array_unique(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            is_array($codes) ? $codes : []
        )));
        $defaultCurrency = strtoupper(trim((string) ($input['defaultCurrency'] ?? '')));

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,31}$/', $agencyUid)) {
            throw new InvalidArgumentException('商户编码必须为 3 到 32 位字母、数字、下划线或短横线');
        }
        if (mb_strlen($showName) < 2 || mb_strlen($showName) > 32) {
            throw new InvalidArgumentException('商户名称必须为 2 到 32 个字符');
        }
        if ($backRate < 0 || $backRate > 100) throw new InvalidArgumentException('返点必须在 0 到 100 之间');
        if (mb_strlen($phone) > 20 || mb_strlen($socialCode) > 20) throw new InvalidArgumentException('联系方式不能超过 20 个字符');
        if ($codes === [] || !in_array($defaultCurrency, $codes, true)) {
            throw new InvalidArgumentException('至少启用一个币种，且默认币种必须在启用列表中');
        }

        $db = Db::connect('game');
        if ($db->table('d_channel_parter')->where('agency_uid', $agencyUid)->find()) {
            throw new RuntimeException('MERCHANT_CODE_CONFLICT');
        }
        $validCodes = array_map('strtoupper', array_map('strval', $db->table('s_currency_profile')
            ->where('status', 1)->whereIn('currency_code', $codes)->column('currency_code')));
        if (array_diff($codes, $validCodes) !== []) throw new InvalidArgumentException('包含未启用或不存在的币种');

        // Compatible with api_service Game::applykey20261103.
        $apiKey = base64_encode(random_bytes(32));
        $now = time();
        try {
            $channelId = (int) $db->transaction(function ($db) use ($agencyUid, $showName, $apiKey, $backRate, $phone, $socialCode, $defaultCurrency, $creditLimit, $purchaseLimit, $status, $codes, $now): int {
                $channelId = (int) $db->table('d_channel_parter')->insertGetId([
                    'agency_uid' => $agencyUid,
                    'apikey' => $apiKey,
                    'show_name' => $showName,
                    'back_rate' => $backRate,
                    'phone' => $phone,
                    'social_code' => $socialCode,
                    'currency_code' => $defaultCurrency,
                    'credit_limit' => $creditLimit,
                    'purchase_limit' => $purchaseLimit,
                    'used_coin' => 0,
                    'status' => $status,
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                foreach ($codes as $code) {
                    $db->table('s_channel_currency')->insert([
                        'channel_id' => $channelId,
                        'currency_code' => $code,
                        'is_default' => $code === $defaultCurrency ? 1 : 0,
                        'status' => 1,
                    ]);
                }
                return $channelId;
            });
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate entry')) throw new RuntimeException('MERCHANT_CODE_CONFLICT', 0, $exception);
            throw $exception;
        }

        $runtimeStatus = 'not_configured';
        $runtimeError = '';
        if ($this->runtime->isConfigured()) {
            try {
                $loaded = $this->runtime->reloadCurrency();
                $runtimeStatus = (!empty($loaded['success']) || ($loaded['message'] ?? '') === 'succ') ? 'loaded' : 'failed';
                if ($runtimeStatus === 'failed') $runtimeError = '运行端未确认新商户币种配置';
            } catch (\Throwable $exception) {
                $runtimeStatus = 'failed';
                $runtimeError = $exception->getMessage();
            }
        }

        $safeAfter = [
            'channelId' => $channelId,
            'agencyUid' => $agencyUid,
            'showName' => $showName,
            'backRate' => $backRate,
            'currencyCodes' => $codes,
            'defaultCurrency' => $defaultCurrency,
            'creditLimit' => $creditLimit,
            'purchaseLimit' => $purchaseLimit,
            'status' => $status,
            'apiKeySuffix' => substr($apiKey, -4),
            'runtimeStatus' => $runtimeStatus,
        ];
        $this->audit->record('merchant.create', $runtimeStatus === 'failed' ? 'failed' : 'success', $ip, $agent,
            (int) $actor['id'], 0, 'platform_admin', 'merchant', (string) $channelId, $runtimeError, null, $safeAfter);

        return $safeAfter + ['apiKey' => $apiKey, 'runtimeError' => $runtimeError];
    }

    private function nonNegativeInteger(mixed $value, string $label): int
    {
        $text = trim((string) $value);
        if (!preg_match('/^\d+$/', $text)) throw new InvalidArgumentException($label . '必须为非负整数');
        $number = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($number === false) throw new InvalidArgumentException($label . '超出允许范围');
        return (int) $number;
    }
}
