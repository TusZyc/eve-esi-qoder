<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestDashboardController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Api\DashboardDataController;
use App\Http\Controllers\Api\AssetDataController;
use App\Http\Controllers\Api\CharacterLocationController;
use App\Http\Controllers\Api\CharacterOnlineController;
use App\Http\Controllers\Api\SkillDataController;
use App\Http\Controllers\Api\ServerStatusController;
use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\SkillController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 首页
Route::get('/', function () {
    return view('welcome');
})->name('home');

// 游客仪表盘（无需授权）
Route::get('/guest', [GuestDashboardController::class, 'index'])->name('guest.dashboard');

// 市场（公开访问）
Route::get('/market', [MarketController::class, 'index'])->name('market.index');

// 公开 API（无需认证）
Route::get('/api/public/server-status', [ServerStatusController::class, 'index'])
    ->middleware('throttle:10,1')
    ->name('api.public.server-status');

// 市场公开 API
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/api/public/market/groups', [MarketDataController::class, 'groups'])->name('api.public.market.groups');
    Route::get('/api/public/market/search', [MarketDataController::class, 'searchItems'])->name('api.public.market.search');
    Route::get('/api/public/market/regions', [MarketDataController::class, 'regions'])->name('api.public.market.regions');
    Route::get('/api/public/market/active-types', [MarketDataController::class, 'activeTypes'])->name('api.public.market.active-types');
    Route::get('/api/public/market/orders', [MarketDataController::class, 'orders'])->name('api.public.market.orders');
    Route::get('/api/public/market/history', [MarketDataController::class, 'history'])->name('api.public.market.history');
    Route::get('/api/public/market/types/{id}', [MarketDataController::class, 'typeDetail'])->name('api.public.market.type-detail');
});

// OAuth2 路由
Route::prefix('auth')->group(function () {
    Route::get('/guide', [AuthController::class, 'guide'])->name('auth.guide');
    Route::get('/callback', [AuthController::class, 'callback'])->name('auth.callback');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

// API 路由（异步数据加载，添加速率限制）
Route::middleware(['auth', 'eve.refresh', 'throttle:30,1'])->prefix('api/dashboard')->group(function () {
    Route::get('/server-status', [DashboardDataController::class, 'serverStatus'])->name('api.dashboard.server-status');
    Route::get('/skills', [DashboardDataController::class, 'skills'])->name('api.dashboard.skills');
    Route::get('/skill-queue', [DashboardDataController::class, 'skillQueue'])->name('api.dashboard.skill-queue');
    Route::get('/assets/locations', [AssetDataController::class, 'locations'])->name('api.dashboard.assets.locations');
    Route::get('/assets/location/{locationId}', [AssetDataController::class, 'locationItems'])->name('api.dashboard.assets.location');
    Route::get('/assets/search', [AssetDataController::class, 'search'])->name('api.dashboard.assets.search');
    Route::get('/character-info', [DashboardDataController::class, 'characterInfo'])->name('api.dashboard.character-info');
    Route::get('/character-location', [CharacterLocationController::class, 'index'])->name('api.dashboard.character-location');
    Route::get('/character-online', [CharacterOnlineController::class, 'index'])->name('api.dashboard.character-online');

    // 技能页面专用 API
    Route::get('/skills/overview', [SkillDataController::class, 'overview'])->name('api.dashboard.skills-overview');
    Route::get('/skills/queue', [SkillDataController::class, 'queue'])->name('api.dashboard.skills-queue');
    Route::get('/skills/groups', [SkillDataController::class, 'groups'])->name('api.dashboard.skills-groups');
});

// 市场认证 API（需登录）
Route::middleware(['auth', 'eve.refresh', 'throttle:30,1'])->prefix('api/market')->group(function () {
    Route::get('/character-orders', [MarketDataController::class, 'characterOrders'])->name('api.market.character-orders');
    Route::get('/my-order-ids', [MarketDataController::class, 'myOrderIds'])->name('api.market.my-order-ids');
});

// 需要认证的路由（自动刷新 EVE Token）
Route::middleware(['auth', 'eve.refresh'])->group(function () {
    // 仪表盘
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // 角色管理
    Route::prefix('characters')->group(function () {
        Route::get('/', [CharacterController::class, 'index'])->name('characters.index');
        Route::get('/{character}', [CharacterController::class, 'show'])->name('characters.show');
        Route::post('/{character}/refresh', [CharacterController::class, 'refresh'])->name('characters.refresh');
        Route::delete('/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');
    });
    
    // 技能队列
    Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
    Route::get('/skills/queue', [CharacterController::class, 'skillQueue'])->name('skills.queue');
    
    // 资产
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    
    // 钱包
    Route::get('/wallet', [CharacterController::class, 'wallet'])->name('wallet.index');
});

// KM 查询（公开访问）
Route::get('/killmails', [\App\Http\Controllers\Api\KillmailController::class, 'index'])->name('killmails.index');

// KM API（公开）
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/api/killmails/autocomplete', [\App\Http\Controllers\Api\KillmailController::class, 'autocomplete']);
    Route::get('/api/killmails/advanced-search', [\App\Http\Controllers\Api\KillmailController::class, 'advancedSearch']);
    Route::get('/api/killmails/search', [\App\Http\Controllers\Api\KillmailController::class, 'search']);
    Route::get('/api/killmails/pilot/{pilotId}/kills', [\App\Http\Controllers\Api\KillmailController::class, 'pilotKills']);
    Route::get('/api/killmails/kill/{killId}', [\App\Http\Controllers\Api\KillmailController::class, 'killDetail']);
});
