<?php

declare(strict_types=1);

namespace app\service;

use think\Response;

final class ApiResponder
{
    public function success(mixed $data = null, string $message = '', int $status = 200): Response
    {
        return json([
            'code' => 'OK',
            'message' => $message,
            'data' => $data,
            'requestId' => $this->requestId(),
        ], $status);
    }

    public function error(string $code, string $message, int $status, array $details = []): Response
    {
        return json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
            'requestId' => $this->requestId(),
        ], $status);
    }

    private function requestId(): string
    {
        return strtoupper(bin2hex(random_bytes(12)));
    }
}
