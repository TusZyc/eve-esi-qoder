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
use App\Http\Controllers\LpStoreController;
use App\Http\Controllers\CapitalNavController;
use App\Http\Controllers\Api\CapitalNavApiController;
use App\Http\Controllers\StandingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\FittingController;
use App\Http\Controllers\CharacterKillmailController;
use App\Http\Controllers\Api\StandingDataController;
use App\Http\Controllers\Api\ContactDataController;
use App\Http\Controllers\Api\NotificationDataController;
use App\Http\Controllers\Api\WalletDataController;
use App\Http\Controllers\Api\BookmarkDataController;
use App\Http\Controllers\Api\ContractDataController;
use App\Http\Controllers\Api\FittingDataController;
use App\Http\Controllers\Api\CharacterKillmailDataController;
use App\Http\Controllers\Api\CharacterDataController;
use App\Http\Controllers\Api\MailDataController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\FleetController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\ScoutController;
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

// 使用指南（公开访问）
Route::get('/guide', function () {
    return view('guide');
})->name('guide');

// 游客仪表盘（无需授权）
Route::get('/guest', [GuestDashboardController::class, 'index'])->name('guest.dashboard');

// 市场（公开访问）
Route::get('/market', [MarketController::class, 'index'])->name('market.index');

// LP 商店（公开访问）
Route::get('/lp-store', [LpStoreController::class, 'index'])->name('lp-store.index');

// 斥候工具（公开访问）
Route::get('/scout', [ScoutController::class, 'index'])->name('scout.index');
Route::post('/scout', [ScoutController::class, 'store'])->name('scout.store');
Route::get('/scout/{id}', [ScoutController::class, 'result'])->name('scout.result');

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

    // 声望 API
    Route::get('/standings', [StandingDataController::class, 'index'])->name('api.dashboard.standings');

    // 联系人 API
    Route::get('/contacts', [ContactDataController::class, 'index'])->name('api.dashboard.contacts');

    // 提醒 API
    Route::get('/notifications', [NotificationDataController::class, 'index'])->name('api.dashboard.notifications');
    Route::get('/notifications/summary', [NotificationDataController::class, 'summary'])->name('api.dashboard.notifications.summary');

    // 钱包 API
    Route::get('/wallet/balance', [WalletDataController::class, 'balance'])->name('api.dashboard.wallet.balance');
    Route::get('/wallet/journal', [WalletDataController::class, 'journal'])->name('api.dashboard.wallet.journal');
    Route::get('/wallet/transactions', [WalletDataController::class, 'transactions'])->name('api.dashboard.wallet.transactions');
    Route::get('/wallet/loyalty', [WalletDataController::class, 'loyalty'])->name('api.dashboard.wallet.loyalty');
    Route::get('/wallet/corp-permission', [WalletDataController::class, 'checkCorpPermission'])->name('api.dashboard.wallet.corp-permission');
    Route::get('/wallet/corporation', [WalletDataController::class, 'corporationBalance'])->name('api.dashboard.wallet.corporation');
    Route::get('/wallet/corporation/journal', [WalletDataController::class, 'corporationJournal'])->name('api.dashboard.wallet.corporation-journal');
    Route::get('/wallet/corporation/transactions', [WalletDataController::class, 'corporationTransactions'])->name('api.dashboard.wallet.corporation-transactions');

    // 书签 API
    Route::get('/bookmarks', [BookmarkDataController::class, 'index'])->name('api.dashboard.bookmarks');
    Route::get('/bookmarks/folders', [BookmarkDataController::class, 'folders'])->name('api.dashboard.bookmarks.folders');

    // 合同 API
    Route::get('/contracts', [ContractDataController::class, 'index'])->name('api.dashboard.contracts');
    Route::get('/contracts/{id}/items', [ContractDataController::class, 'items'])->name('api.dashboard.contracts.items');

    // 装配 API
    Route::get('/fittings', [FittingDataController::class, 'index'])->name('api.dashboard.fittings');

    // 击毁报告 API
    Route::get('/killmails', [CharacterKillmailDataController::class, 'index'])->name('api.dashboard.killmails');

    // 角色详细数据 API
    Route::get('/character/attributes', [CharacterDataController::class, 'attributes'])->name('api.dashboard.character.attributes');
    Route::get('/character/implants', [CharacterDataController::class, 'implants'])->name('api.dashboard.character.implants');
    Route::get('/character/clones', [CharacterDataController::class, 'clones'])->name('api.dashboard.character.clones');
    Route::get('/character/corphistory', [CharacterDataController::class, 'corpHistory'])->name('api.dashboard.character.corphistory');

    // 邮件 API
    Route::get('/mail', [MailDataController::class, 'index'])->name('api.dashboard.mail');
    Route::get('/mail/labels', [MailDataController::class, 'labels'])->name('api.dashboard.mail.labels');
    Route::get('/mail/lists', [MailDataController::class, 'lists'])->name('api.dashboard.mail.lists');
    Route::get('/mail/{mail_id}', [MailDataController::class, 'show'])->name('api.dashboard.mail.show');
    Route::post('/mail', [MailDataController::class, 'store'])->name('api.dashboard.mail.store');
    Route::put('/mail/{mail_id}', [MailDataController::class, 'update'])->name('api.dashboard.mail.update');
    Route::delete('/mail/{mail_id}', [MailDataController::class, 'destroy'])->name('api.dashboard.mail.destroy');
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
        Route::delete('/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');
    });
    
    // 技能队列
    Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
    Route::get('/skills/queue', [CharacterController::class, 'skillQueue'])->name('skills.queue');
    
    // 资产
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    
    // 钱包
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');

    // 声望
    Route::get('/standings', [StandingController::class, 'index'])->name('standings.index');

    // 联系人
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');

    // 提醒
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // 书签
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');

    // 合同
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');

    // 装配
    Route::get('/fittings', [FittingController::class, 'index'])->name('fittings.index');

    // 击毁报告
    Route::get('/my-killmails', [CharacterKillmailController::class, 'index'])->name('character-killmails.index');

    // 邮件
    Route::get('/mail', [MailController::class, 'index'])->name('mail.index');

    // 舰队出勤统计
    Route::prefix('fleet')->group(function () {
        Route::get('/', [FleetController::class, 'index'])->name('fleet.index');
        Route::get('/create', [FleetController::class, 'create'])->name('fleet.create');
        Route::post('/', [FleetController::class, 'store'])->name('fleet.store');
        Route::get('/check-status', [FleetController::class, 'checkStatus'])->name('fleet.check-status');
        Route::get('/{id}', [FleetController::class, 'show'])->name('fleet.show');
        Route::post('/{id}/snapshot', [FleetController::class, 'snapshot'])->name('fleet.snapshot');
        Route::post('/{id}/end', [FleetController::class, 'end'])->name('fleet.end');
        Route::get('/{id}/report', [FleetController::class, 'report'])->name('fleet.report');
        Route::get('/{id}/export', [FleetController::class, 'export'])->name('fleet.export');
        Route::get('/{id}/members', [FleetController::class, 'members'])->name('fleet.members');
    });
});

// LP 商店公开 API
Route::middleware(['throttle:30,1'])->prefix('api/public/lp-store')->group(function () {
    Route::get('/factions', [LpStoreController::class, 'factions'])->name('api.public.lp-store.factions');
    Route::get('/offers', [LpStoreController::class, 'offers'])->name('api.public.lp-store.offers');
    Route::get('/history', [LpStoreController::class, 'history'])->name('api.public.lp-store.history');
    Route::get('/orders', [LpStoreController::class, 'orders'])->name('api.public.lp-store.orders');
});

// KM 查询（公开访问）
Route::get('/killmails', [\App\Http\Controllers\Api\KillmailController::class, 'index'])->name('killmails.index');

// 战场报告（公开访问）
Route::get('/killmails/battle', [\App\Http\Controllers\Api\BattleReportController::class, 'index'])->name('battlereport.index');

// KM API（公开）
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/api/killmails/autocomplete', [\App\Http\Controllers\Api\KillmailController::class, 'autocomplete']);
    Route::get('/api/killmails/advanced-search', [\App\Http\Controllers\Api\KillmailController::class, 'advancedSearch']);
    Route::get('/api/killmails/search', [\App\Http\Controllers\Api\KillmailController::class, 'search']);
    Route::get('/api/killmails/pilot/{pilotId}/kills', [\App\Http\Controllers\Api\KillmailController::class, 'pilotKills']);
    Route::get('/api/killmails/kill/{killId}', [\App\Http\Controllers\Api\KillmailController::class, 'killDetail']);
    Route::get('/api/killmails/kill/{killId}/image', [\App\Http\Controllers\Api\KillmailController::class, 'killImage']);
    
    // 战场报告 API
    Route::post('/api/battlereport/search', [\App\Http\Controllers\Api\BattleReportController::class, 'search']);
    Route::post('/api/battlereport/generate', [\App\Http\Controllers\Api\BattleReportController::class, 'generate']);
});

// 旗舰导航（公开访问）
Route::get('/capital-nav', [CapitalNavController::class, 'index'])->name('capital-nav.index');

// 旗舰导航 API（公开）
Route::middleware('throttle:30,1')->prefix('api/capital-nav')->group(function () {
    Route::get('/autocomplete', [CapitalNavApiController::class, 'systemAutocomplete']);
    Route::get('/distance', [CapitalNavApiController::class, 'distance']);
    Route::get('/reachable', [CapitalNavApiController::class, 'reachableSystems']);
    Route::get('/route', [CapitalNavApiController::class, 'planRoute']);
});

// 站点管理后台
Route::middleware(['auth', 'eve.refresh', 'site.admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/logs', [AdminController::class, 'logs'])->name('admin.logs');
    Route::get('/api-stats', [AdminController::class, 'apiStats'])->name('admin.api-stats');
    Route::get('/cache', [AdminController::class, 'cache'])->name('admin.cache');
    Route::get('/data', [AdminController::class, 'dataManage'])->name('admin.data');
    Route::get('/api/dashboard-data', [AdminController::class, 'dashboardData'])->name('admin.api.dashboard-data');
    Route::get('/api/api-stats-data', [AdminController::class, 'apiStatsData'])->name('admin.api.api-stats-data');
    Route::get('/api/cache-data', [AdminController::class, 'cacheData'])->name('admin.api.cache-data');
    Route::post('/api/clear-cache', [AdminController::class, 'clearCache'])->name('admin.api.clear-cache');
    Route::get('/api/data-info', [AdminController::class, 'dataInfo'])->name('admin.api.data-info');
    Route::post('/users/{userId}/refresh-token', [AdminController::class, 'refreshToken'])->name('admin.users.refresh-token');
});

// 虫洞查询（公开访问）
Route::get('/wormhole', [\App\Http\Controllers\WormholeController::class, 'index'])->name('wormhole.index');
Route::get('/wormhole/{systemName}', [\App\Http\Controllers\WormholeController::class, 'show'])->name('wormhole.show');

// 虫洞公开 API
Route::middleware('throttle:30,1')->prefix('api/public/wormhole')->group(function () {
    Route::get('/search', [\App\Http\Controllers\Api\WormholeApiController::class, 'search'])->name('api.public.wormhole.search');
    Route::get('/autocomplete', [\App\Http\Controllers\Api\WormholeApiController::class, 'autocomplete'])->name('api.public.wormhole.autocomplete');
    Route::get('/system/{systemId}', [\App\Http\Controllers\Api\WormholeApiController::class, 'systemInfo'])->name('api.public.wormhole.system');
    Route::get('/system/{systemId}/kills', [\App\Http\Controllers\Api\WormholeApiController::class, 'systemKills'])->name('api.public.wormhole.kills');
    Route::get('/systems', [\App\Http\Controllers\Api\WormholeApiController::class, 'systemsList'])->name('api.public.wormhole.systems');
    Route::get('/types', [\App\Http\Controllers\Api\WormholeApiController::class, 'typesList'])->name('api.public.wormhole.types');
    Route::get('/effects', [\App\Http\Controllers\Api\WormholeApiController::class, 'effectsList'])->name('api.public.wormhole.effects');
});
