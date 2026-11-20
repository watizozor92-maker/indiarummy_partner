<?php

declare(strict_types=1);

return [
    'url' => (string) env('INTERNAL_GAME_API_URL', ''),
    'secret' => (string) env('INTERNAL_GAME_API_SECRET', ''),
    'connect_timeout' => 3,
    'timeout' => 30,
];
