<?php

declare(strict_types=1);

use app\middleware\Authenticate;
use app\middleware\RequirePermission;
use think\facade\Route;

Route::get('api/v1/health', 'Health/index');

Route::get('login', 'Auth/login');
Route::post('login', 'Auth/authenticate');

Route::get('dashboard', 'Dashboard/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'dashboard.view');

Route::get('players', 'Players/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'players.view');
Route::get('rounds', 'Rounds/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'rounds.view');
Route::get('merchants', 'Merchants/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.view');
Route::get('statistics', 'Statistics/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'statistics.view');
Route::get('game-control', 'GameControl/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.view');
Route::get('player-control', 'PlayerControl/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'player_control.view');
Route::get('inventory', 'Inventory/overview')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'inventory.view');
Route::get('inventory/ledger', 'Inventory/ledger')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'inventory.view');
Route::get('inventory/adjustments', 'Inventory/adjustments')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'inventory.adjust');
Route::get('inventory/grants', 'Inventory/grants')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchants.manage');
Route::get('audit', 'Audit/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'audit.view_self');

Route::get('api/v1/overview', 'api.Overview/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'dashboard.view');
Route::get('api/v1/session', 'api.Session/index')->middleware(Authenticate::class);
Route::post('api/v1/auth/password', 'api.Account/password')->middleware(Authenticate::class);
Route::get('api/v1/merchants/options', 'api.Merchants/options')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.view');
Route::get('api/v1/merchants/currency-options', 'api.Merchants/currencyOptions')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.view');
Route::post('api/v1/merchants', 'api.Merchants/create')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.create');
Route::put('api/v1/merchants/<channelId>/currencies', 'api.Merchants/updateCurrencies')
    ->pattern(['channelId' => '\d+'])
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.manage');
Route::get('api/v1/merchants/<channelId>', 'api.Merchants/detail')
    ->pattern(['channelId' => '\d+'])
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.view');
Route::get('api/v1/merchants', 'api.Merchants/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'merchants.view');
Route::get('api/v1/players/<uid>', 'api.Players/detail')
    ->pattern(['uid' => '\d+'])
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'players.view');
Route::get('api/v1/players-export', 'api.Players/export')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'players.view');
Route::get('api/v1/players', 'api.Players/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'players.view');
Route::get('api/v1/rounds/<id>', 'api.Rounds/detail')
    ->pattern(['id' => '\d+'])
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'rounds.view');
Route::get('api/v1/rounds-export', 'api.Rounds/export')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'rounds.view');
Route::get('api/v1/rounds', 'api.Rounds/index')
    ->middleware(Authenticate::class)
    ->middleware(RequirePermission::class, 'rounds.view');
Route::get('api/v1/statistics', 'api.Statistics/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'statistics.view');
Route::get('api/v1/statistics-export', 'api.Statistics/export')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'statistics.view');
Route::get('api/v1/game-control/<gameId>/presets', 'api.GameControl/presets')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.view');
Route::get('api/v1/game-control/<gameId>/revisions', 'api.GameControl/revisions')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.view');
Route::get('api/v1/game-control/<gameId>/runtime-status', 'api.GameControl/runtimeStatus')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.view');
Route::post('api/v1/game-control/<gameId>/preview', 'api.GameControl/preview')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.preview');
Route::post('api/v1/game-control/<gameId>/publish', 'api.GameControl/publish')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.publish_authorized');
Route::post('api/v1/game-control/<gameId>/rollback', 'api.GameControl/rollback')->pattern(['gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.publish_authorized');
Route::get('api/v1/merchants/<channelId>/control-grants', 'api.GameControl/grants')->pattern(['channelId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchants.view');
Route::put('api/v1/merchants/<channelId>/games/<gameId>/control-grant', 'api.GameControl/setGrant')->pattern(['channelId'=>'\d+','gameId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchants.manage');
Route::get('api/v1/game-control', 'api.GameControl/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'game_control.view');
Route::get('api/v1/player-control/options','api.PlayerControl/options')->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.view');
Route::get('api/v1/player-control-export','api.PlayerControl/export')->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.view');
Route::get('api/v1/player-control/<id>','api.PlayerControl/detail')->pattern(['id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.view');
Route::get('api/v1/player-control','api.PlayerControl/index')->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.view');
Route::post('api/v1/player-control','api.PlayerControl/create')->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.manage');
Route::post('api/v1/player-control/<id>/cancel','api.PlayerControl/cancel')->pattern(['id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'player_control.manage');
Route::get('api/v1/inventory/access','api.Inventory/access')->middleware(Authenticate::class)->middleware(RequirePermission::class,'inventory.view');
Route::get('api/v1/inventory/overview','api.Inventory/overview')->middleware(Authenticate::class)->middleware(RequirePermission::class,'inventory.view');
Route::get('api/v1/inventory/ledger','api.Inventory/ledger')->middleware(Authenticate::class)->middleware(RequirePermission::class,'inventory.view');
Route::get('api/v1/inventory/adjustments','api.Inventory/adjustments')->middleware(Authenticate::class)->middleware(RequirePermission::class,'inventory.view');
Route::post('api/v1/inventory/adjustments','api.Inventory/createAdjustment')->middleware(Authenticate::class)->middleware(RequirePermission::class,'inventory.adjust');
Route::post('api/v1/inventory/adjustments/<id>/approve','api.Inventory/approve')->pattern(['id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'merchants.manage');
Route::post('api/v1/inventory/adjustments/<id>/reject','api.Inventory/reject')->pattern(['id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'merchants.manage');
Route::get('api/v1/merchants/<channelId>/inventory-grant','api.Inventory/grant')->pattern(['channelId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'merchants.manage');
Route::put('api/v1/merchants/<channelId>/inventory-grant','api.Inventory/saveGrant')->pattern(['channelId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class,'merchants.manage');
Route::get('api/v1/audit-logs', 'api.Audit/index')->middleware(Authenticate::class)->middleware(RequirePermission::class, 'audit.view_self');
Route::get('api/v1/merchants/<channelId>/ip-whitelist', 'api.IpWhitelist/index')->pattern(['channelId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchant.security.view');
Route::post('api/v1/merchants/<channelId>/ip-whitelist', 'api.IpWhitelist/create')->pattern(['channelId'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchant.security.manage');
Route::put('api/v1/merchants/<channelId>/ip-whitelist/<id>', 'api.IpWhitelist/update')->pattern(['channelId'=>'\d+','id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchant.security.manage');
Route::delete('api/v1/merchants/<channelId>/ip-whitelist/<id>', 'api.IpWhitelist/delete')->pattern(['channelId'=>'\d+','id'=>'\d+'])->middleware(Authenticate::class)->middleware(RequirePermission::class, 'merchant.security.manage');

Route::post('logout', 'Auth/logout')
    ->middleware(Authenticate::class);

Route::get('/', static fn () => redirect('/dashboard'));
