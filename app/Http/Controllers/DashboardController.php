<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Helpers\EveHelper;
use App\Services\TokenRefreshService;

class DashboardController extends Controller
{
    /**
     * 显示仪表盘（快速加载，数据异步获取）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 检查是否需要刷新 Token（使用统一的 TokenRefreshService）
        if ($user->isTokenExpired() || $user->shouldRefreshToken()) {
            Log::info('检测到 Token 需要刷新，过期时间：' . $user->getTokenExpiryFormatted());
            TokenRefreshService::refresh($user);
        } else {
            Log::info('Token 有效，剩余时间：' . $user->getTokenRemainingSeconds() . ' 秒');
        }
        
        // 立即返回页面，数据由前端异步加载
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('dashboard', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
