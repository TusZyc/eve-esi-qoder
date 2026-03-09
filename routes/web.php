<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CharacterController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 首页
Route::get('/', function () {
    return view('welcome');
})->name('home');

// OAuth2 路由
Route::prefix('auth')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::get('/callback', [AuthController::class, 'callback'])->name('auth.callback');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

// 需要认证的路由
Route::middleware(['auth'])->group(function () {
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
    Route::get('/skills/queue', [CharacterController::class, 'skillQueue'])->name('skills.queue');
    
    // 资产
    Route::get('/assets', [CharacterController::class, 'assets'])->name('assets.index');
    
    // 钱包
    Route::get('/wallet', [CharacterController::class, 'wallet'])->name('wallet.index');
});
