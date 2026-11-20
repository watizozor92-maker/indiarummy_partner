<?php

declare(strict_types=1);

return [
    'roles' => [
        'platform_admin' => ['*'],
        'merchant_admin' => [
            'dashboard.view',
            'players.view',
            'rounds.view',
            'statistics.view',
            'game_control.view',
            'game_control.publish_authorized',
            'game_control.preview',
            'player_control.view',
            'player_control.manage',
            'inventory.view',
            'inventory.adjust',
            'audit.view_self',
            'merchant.security.view',
            'merchant.security.manage',
        ],
        'merchant_viewer' => [
            'dashboard.view',
            'players.view',
            'rounds.view',
            'statistics.view',
            'game_control.view',
            'player_control.view',
            'inventory.view',
            'audit.view_self',
            'merchant.security.view',
        ],
    ],
];
