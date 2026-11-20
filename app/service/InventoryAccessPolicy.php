<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;

final class InventoryAccessPolicy
{
    public const VISIBILITIES = ['none', 'summary', 'full'];
    public const ADJUST_MODES = ['none', 'request', 'direct'];

    public function normalizeGrant(array $input): array
    {
        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'none')));
        $adjustMode = strtolower(trim((string) ($input['adjustMode'] ?? 'none')));
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            throw new InvalidArgumentException('库存显示级别无效');
        }
        if (!in_array($adjustMode, self::ADJUST_MODES, true)) {
            throw new InvalidArgumentException('库存调整模式无效');
        }
        if ($visibility === 'none') $adjustMode = 'none';

        $singleLimit = $this->nonNegativeAmount($input['singleLimit'] ?? 0, '单次调整上限');
        $dailyLimit = $this->nonNegativeAmount($input['dailyLimit'] ?? 0, '每日调整上限');
        if ($adjustMode === 'direct' && ($singleLimit <= 0 || $dailyLimit <= 0)) {
            throw new InvalidArgumentException('直接调整模式必须设置大于零的单次和每日上限');
        }
        if ($dailyLimit > 0 && $singleLimit > $dailyLimit) {
            throw new InvalidArgumentException('单次调整上限不能超过每日调整上限');
        }

        return [
            'visibility' => $visibility,
            'canViewLedger' => $visibility !== 'none' && (int) ($input['canViewLedger'] ?? 0) === 1,
            'adjustMode' => $adjustMode,
            'gameIds' => $this->gameIds($input['gameIds'] ?? []),
            'currencyCodes' => $this->currencyCodes($input['currencyCodes'] ?? []),
            'singleLimit' => $singleLimit,
            'dailyLimit' => $dailyLimit,
        ];
    }

    public function scopeAllows(array $grant, int $gameId, string $currencyCode): bool
    {
        $games = array_map('intval', (array) ($grant['gameIds'] ?? []));
        $currencies = array_map('strtoupper', array_map('strval', (array) ($grant['currencyCodes'] ?? [])));

        return ($games === [] || in_array($gameId, $games, true))
            && ($currencies === [] || in_array(strtoupper($currencyCode), $currencies, true));
    }

    public function canDirect(float $amount, float $dailyUsed, float $singleLimit, float $dailyLimit): bool
    {
        return $amount > 0
            && $singleLimit > 0
            && $dailyLimit > 0
            && $amount <= $singleLimit + 0.00001
            && $dailyUsed + $amount <= $dailyLimit + 0.00001;
    }

    private function gameIds(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,，]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($values ?: [] as $item) {
            if (!preg_match('/^\d+$/', trim((string) $item)) || (int) $item <= 0) {
                throw new InvalidArgumentException('游戏范围必须填写正整数游戏 ID');
            }
            $ids[] = (int) $item;
        }

        return array_values(array_unique($ids));
    }

    private function currencyCodes(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,，]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $codes = [];
        foreach ($values ?: [] as $item) {
            $code = strtoupper(trim((string) $item));
            if (!preg_match('/^[A-Z0-9]{2,12}$/', $code)) {
                throw new InvalidArgumentException('币种范围包含无效代码');
            }
            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }

    private function nonNegativeAmount(mixed $value, string $label): float
    {
        $text = trim((string) $value);
        if ($text === '' || !preg_match('/^\d+(?:\.\d{1,4})?$/', $text)) {
            throw new InvalidArgumentException($label . '必须为最多四位小数的非负数字');
        }
        $amount = (float) $text;
        if (!is_finite($amount) || $amount < 0) {
            throw new InvalidArgumentException($label . '无效');
        }

        return $amount;
    }
}
