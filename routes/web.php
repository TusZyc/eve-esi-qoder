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
use App\Http\Controllers\Api\KillmailController;
use App\Http\Controllers\Api\SystemDistanceController;
use App\Http\Controllers\CapitalNavController;
use App\Http\Controllers\Api\CapitalNavApiController;
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
    
    // 角色数据聚合 API（并行获取属性、植入体、克隆体、雇佣历史）
    Route::get('/character/all-data', [CharacterController::class, 'allData'])->name('api.dashboard.character-all-data');
    
    // 角色详细信息 API
    Route::get('/character/attributes', [CharacterController::class, 'attributes'])->name('api.dashboard.character-attributes');
    Route::get('/character/implants', [CharacterController::class, 'implants'])->name('api.dashboard.character-implants');
    Route::get('/character/clones', [CharacterController::class, 'clones'])->name('api.dashboard.character-clones');
    Route::get('/character/corphistory', [CharacterController::class, 'corporationHistory'])->name('api.dashboard.character-corphistory');
});

// KM 查询（公开访问）
Route::get('/killmails', [KillmailController::class, 'index'])->name('killmails.index');

// 旗舰导航（公开访问）
Route::get('/capital-nav', [CapitalNavController::class, 'index'])->name('capital-nav.index');

// 旗舰导航 API
Route::middleware('throttle:30,1')->prefix('api/capital-nav')->group(function () {
    Route::get('/autocomplete', [CapitalNavApiController::class, 'systemAutocomplete'])->name('api.capital-nav.autocomplete');
    Route::get('/distance', [CapitalNavApiController::class, 'distance'])->name('api.capital-nav.distance');
    Route::get('/reachable', [CapitalNavApiController::class, 'reachableSystems'])->name('api.capital-nav.reachable');
    Route::get('/route', [CapitalNavApiController::class, 'planRoute'])->name('api.capital-nav.route');
});

// KM 查询 API
Route::middleware('throttle:30,1')->prefix('api/killmails')->group(function () {
    Route::get('/search', [KillmailController::class, 'search'])->name('api.killmails.search');
    Route::get('/autocomplete', [KillmailController::class, 'autocomplete'])->name('api.killmails.autocomplete');
    Route::get('/advanced-search', [KillmailController::class, 'advancedSearch'])->name('api.killmails.advanced-search');
    Route::get('/pilot/{pilotId}/kills', [KillmailController::class, 'pilotKills'])->name('api.killmails.pilot-kills');
    Route::get('/kill/{killId}', [KillmailController::class, 'killDetail'])->name('api.killmails.detail');
});

// 星系距离查询 API
Route::middleware('throttle:30,1')->prefix('api/system-distance')->group(function () {
    Route::get('/path', [SystemDistanceController::class, 'pathDistance'])->name('api.system-distance.path');
    Route::get('/euclidean', [SystemDistanceController::class, 'euclideanDistance'])->name('api.system-distance.euclidean');
    Route::get('/name', [SystemDistanceController::class, 'systemName'])->name('api.system-distance.name');
    Route::get('/batch', [SystemDistanceController::class, 'batchInfo'])->name('api.system-distance.batch');
});

// 市场认证 API（需登录，自动刷新 Token）
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
