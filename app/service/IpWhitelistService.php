<?php

declare(strict_types=1);

namespace app\service;

use think\facade\Db;

final class IpWhitelistService
{
    public function isAllowed(int $channelId, string $ipAddress): bool
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $rows = Db::connect('partner')->table('partner_admin_ip_whitelist')
            ->where('channel_id', $channelId)
            ->where('status', 1)
            ->column('ip_or_cidr');

        foreach ($rows as $network) {
            if ($this->contains((string) $network, $ipAddress)) {
                return true;
            }
        }

        return false;
    }

    public function isValidRule(string $network): bool
    {
        if (!str_contains($network, '/')) {
            return filter_var($network, FILTER_VALIDATE_IP) !== false;
        }

        [$address, $prefix] = array_pad(explode('/', $network, 2), 2, null);
        if ($address === null || $prefix === null || filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $maxPrefix = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= $maxPrefix;
    }

    public function contains(string $network, string $ipAddress): bool
    {
        if (!str_contains($network, '/')) {
            return hash_equals(strtolower($network), strtolower($ipAddress));
        }

        if (!$this->isValidRule($network)) {
            return false;
        }

        [$address, $prefix] = explode('/', $network, 2);
        $networkBytes = inet_pton($address);
        $ipBytes = inet_pton($ipAddress);
        if ($networkBytes === false || $ipBytes === false || strlen($networkBytes) !== strlen($ipBytes)) {
            return false;
        }

        $bits = (int) $prefix;
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($networkBytes, 0, $fullBytes) !== substr($ipBytes, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($networkBytes[$fullBytes]) & $mask) === (ord($ipBytes[$fullBytes]) & $mask);
    }
}
