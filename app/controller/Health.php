<?php

declare(strict_types=1);

namespace app\controller;

use DateTimeImmutable;
use DateTimeZone;
use think\Response;

final class Health
{
    public function index(): Response
    {
        return json([
            'code' => 'OK',
            'message' => '',
            'data' => [
                'service' => 'partner-game-console',
                'status' => 'up',
                'time' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            ],
        ]);
    }
}
