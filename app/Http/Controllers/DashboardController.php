<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Helpers\EveHelper;

class DashboardController extends Controller
{
    /**
     * 显示仪表盘（快速加载，数据异步获取）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 检查是否需要刷新 Token（后台静默刷新）
        if ($user->isTokenExpired() || $user->shouldRefreshToken()) {
            Log::info('检测到 Token 需要刷新，过期时间：' . $user->getTokenExpiryFormatted());
            $this->refreshToken($user);
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
    
    /**
     * 刷新 Token
     */
    private function refreshToken(User $user)
    {
        try {
            Log::info('=== 开始刷新 Token ===');
            Log::info('角色：' . $user->name . ' (ID: ' . $user->eve_character_id . ')');
            
            // 国服 OAuth2 不需要 client_secret，直接在 body 中传递 client_id
            // 设置超时时间为 10 秒，避免服务器维护时卡住
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post(config('esi.oauth_url') . 'token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]);
            
            Log::info('Token 刷新响应状态：' . $response->status());
            
            if ($response->ok()) {
                $tokenData = $response->json();
                Log::info('✅ Token 刷新成功');
                
                $user->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                ]);
                
                Log::info('✅ Token 已更新到数据库');
            } else {
                Log::error('❌ Token 刷新失败：' . $response->body());
                
                // 如果刷新失败（可能是 Refresh Token 过期），需要重新授权
                if ($response->status() === 400 || $response->status() === 401) {
                    Log::warning('Refresh Token 可能已过期，需要重新授权');
                    session()->flash('error', '授权已过期，请重新授权。');
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('❌ Token 刷新连接失败：' . $e->getMessage());
            // 连接失败时不显示错误，可能是服务器维护，用户无感知即可
        } catch (\Exception $e) {
            Log::error('❌ Token 刷新异常：' . $e->getMessage());
        }
    }
}
