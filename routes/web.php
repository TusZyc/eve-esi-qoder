<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Api\DashboardDataController;
use App\Http\Controllers\Api\AssetDataController;
use App\Http\Controllers\Api\CharacterLocationController;
use App\Http\Controllers\Api\CharacterOnlineController;
use App\Http\Controllers\Api\SkillDataController;
use App\Http\Controllers\Api\ServerStatusController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\Api\KillmailController;
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

// 公开 API（无需认证）
Route::get('/api/public/server-status', [ServerStatusController::class, 'index'])
    ->middleware('throttle:10,1')
    ->name('api.public.server-status');

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
    
    // KM 查询
    Route::get('/killmails', [KillmailController::class, 'index'])->name('killmails.index');
    
    // KM 查询 API
    Route::prefix('api/killmails')->group(function () {
        Route::get('/search', [KillmailController::class, 'search'])->name('api.killmails.search');
        Route::get('/pilot/{pilotId}/kills', [KillmailController::class, 'pilotKills'])->name('api.killmails.pilot-kills');
        Route::get('/kill/{killId}', [KillmailController::class, 'killDetail'])->name('api.killmails.detail');
    });
});
