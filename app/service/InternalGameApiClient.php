<?php

declare(strict_types=1);

namespace app\service;

use RuntimeException;

final class InternalGameApiClient
{
    public function __construct(private readonly InternalApiSigner $signer){}
    public function isConfigured(): bool
    {
        return filter_var(config('internal_api.url'), FILTER_VALIDATE_URL) !== false
            && strlen((string) config('internal_api.secret')) >= 32;
    }

    public function preview(int $gameId, array $policies): array
    {
        return $this->request('controlpreview', ['gameid' => $gameId], json_encode($policies, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function reloadChannel(int $channelId, int $gameId): array
    {
        return $this->request('reloadChannel', ['channelid' => $channelId, 'gameid' => $gameId, 'subact' => 'publish']);
    }

    public function reloadCurrency(): array
    {
        return $this->request('reloadcurrency', []);
    }

    public function reloadPlayerControl(int $channelId, int $uid, int $gameId, string $currencyCode): array
    {
        return $this->request('reloadplayercontrol', ['channelid'=>$channelId,'uid'=>$uid,'gameid'=>$gameId,'currencycode'=>strtoupper($currencyCode)]);
    }

    public function status(int $channelId, int $gameId, string $currencyCode): array
    {
        return $this->request('controlstatus', ['channelid' => $channelId, 'gameid' => $gameId, 'currencycode' => strtoupper($currencyCode)]);
    }

    public function adjustCashReserve(int $channelId, int $gameId, string $currencyCode, float $delta, array $operator): array
    {
        return $this->request('adjustcashreserve', [
            'channelid' => $channelId,
            'gameid' => $gameId,
            'currencycode' => strtoupper($currencyCode),
            'delta' => number_format($delta, 4, '.', ''),
            'operatorid' => (int) ($operator['id'] ?? 0),
            'operatorname' => (string) ($operator['name'] ?? ''),
            'operatorip' => (string) ($operator['ip'] ?? ''),
            'reason' => (string) ($operator['reason'] ?? ''),
            'source' => (string) ($operator['source'] ?? 'partner_console'),
        ]);
    }

    private function request(string $action, array $parameters, string $body = ''): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('内部游戏管理 API 尚未配置');
        }
        $timestamp = time();
        $nonce = bin2hex(random_bytes(24));
        $signed = array_merge(['act' => $action], $parameters);
        $query = array_merge(['mod' => 'partnercontrol'], $signed, [
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $this->signer->sign($timestamp,$nonce,$signed,$body,(string) config('internal_api.secret')),
        ]);
        $handle = curl_init(rtrim((string) config('internal_api.url'), '?&') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) config('internal_api.connect_timeout', 3),
            CURLOPT_TIMEOUT => (int) config('internal_api.timeout', 30),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false || $error !== '') throw new RuntimeException('内部游戏管理 API 连接失败');
        if ($status < 200 || $status >= 300) throw new RuntimeException('内部游戏管理 API 返回错误：' . mb_substr((string) $raw, 0, 300));
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) return $decoded;
        return ['success' => trim((string) $raw) === 'succ', 'message' => trim((string) $raw)];
    }
}
