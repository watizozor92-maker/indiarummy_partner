<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use RuntimeException;
use think\facade\Db;

final class MerchantCurrencyService
{
    public function __construct(private readonly AuditService $audit, private readonly InternalGameApiClient $runtime)
    {
    }

    public function options(): array
    {
        return Db::connect('game')->table('s_currency_profile')
            ->where('status', 1)
            ->field('currency_code,currency_name,symbol,decimal_places')
            ->order('currency_code')->select()->toArray();
    }

    /** @param list<string> $currencyCodes */
    public function replace(int $channelId, array $currencyCodes, string $defaultCurrency, array $actor, string $ip, string $agent): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            $currencyCodes
        ))));
        $defaultCurrency = strtoupper(trim($defaultCurrency));
        if ($codes === [] || !in_array($defaultCurrency, $codes, true)) {
            throw new InvalidArgumentException('至少启用一个币种，且默认币种必须在启用列表中');
        }
        if (!Db::connect('game')->table('d_channel_parter')->where('id', $channelId)->find()) {
            throw new RuntimeException('商户不存在');
        }
        $valid = Db::connect('game')->table('s_currency_profile')->where('status', 1)
            ->whereIn('currency_code', $codes)->column('currency_code');
        $valid = array_map('strtoupper', array_map('strval', $valid));
        if (array_diff($codes, $valid) !== []) {
            throw new InvalidArgumentException('包含未启用或不存在的币种');
        }

        $db = Db::connect('game');
        $before = $db->table('s_channel_currency')->where('channel_id', $channelId)
            ->field('currency_code,is_default,status')->order('currency_code')->select()->toArray();
        $db->transaction(function ($db) use ($channelId, $codes, $defaultCurrency): void {
            $db->table('s_channel_currency')->where('channel_id', $channelId)->update(['status' => 0, 'is_default' => 0]);
            foreach ($codes as $code) {
                $values = ['is_default' => $code === $defaultCurrency ? 1 : 0, 'status' => 1];
                $existing = $db->table('s_channel_currency')->where(['channel_id' => $channelId, 'currency_code' => $code])->find();
                if (is_array($existing)) {
                    $db->table('s_channel_currency')->where(['channel_id' => $channelId, 'currency_code' => $code])->update($values);
                } else {
                    $db->table('s_channel_currency')->insert($values + ['channel_id' => $channelId, 'currency_code' => $code]);
                }
            }
            $db->table('d_channel_parter')->where('id', $channelId)->update(['currency_code' => $defaultCurrency]);
        });
        $after = $db->table('s_channel_currency')->where('channel_id', $channelId)
            ->field('currency_code,is_default,status')->order('currency_code')->select()->toArray();
        $runtimeStatus = 'not_configured';
        $runtimeError = '';
        if ($this->runtime->isConfigured()) {
            try {
                $loaded = $this->runtime->reloadCurrency();
                $runtimeStatus = (!empty($loaded['success']) || ($loaded['message'] ?? '') === 'succ') ? 'loaded' : 'failed';
                if ($runtimeStatus === 'failed') $runtimeError = '运行端未确认币种配置加载';
            } catch (\Throwable $exception) {
                $runtimeStatus = 'failed';
                $runtimeError = $exception->getMessage();
            }
        }
        $this->audit->record('merchant.currencies.update', $runtimeStatus === 'failed' ? 'failed' : 'success', $ip, $agent, (int) $actor['id'], (int) $actor['channel_id'], (string) $actor['role_code'], 'merchant', (string) $channelId, $runtimeError, $before, $after, ['runtimeStatus' => $runtimeStatus]);

        return ['currencies' => $after, 'runtimeStatus' => $runtimeStatus, 'runtimeError' => $runtimeError];
    }
}
