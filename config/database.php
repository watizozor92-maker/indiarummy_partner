<?php

declare(strict_types=1);

$connectionFromUrl = static function (string $variable, string $defaultDatabase): array {
    $url = (string) (getenv($variable) ?: '');
    $parts = $url !== '' ? parse_url($url) : false;

    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql') {
        throw new RuntimeException(sprintf('%s must be a valid mysql:// URL', $variable));
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);

    return [
        'type'            => 'mysql',
        'hostname'        => (string) ($parts['host'] ?? '127.0.0.1'),
        'database'        => rawurldecode(ltrim((string) ($parts['path'] ?? $defaultDatabase), '/')),
        'username'        => rawurldecode((string) ($parts['user'] ?? '')),
        'password'        => rawurldecode((string) ($parts['pass'] ?? '')),
        'hostport'        => (string) ($parts['port'] ?? 3306),
        'params'          => [],
        'charset'         => (string) ($query['charset'] ?? 'utf8mb4'),
        'prefix'          => '',
        'deploy'          => 0,
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'fields_strict'   => true,
        'break_reconnect' => false,
        'trigger_sql'     => (bool) env('app_debug', false),
        'fields_cache'    => false,
    ];
};

return [
    'default'         => 'partner',
    'time_query_rule' => [],
    'auto_timestamp'  => true,
    'datetime_format' => 'Y-m-d H:i:s',
    'datetime_field'  => '',
    'connections'     => [
        'partner' => $connectionFromUrl('PARTNER_DATABASE_URL', 'indiarummy_partner'),
        'game'    => $connectionFromUrl('GAME_DATABASE_URL', 'indiarummy_game'),
    ],
];
