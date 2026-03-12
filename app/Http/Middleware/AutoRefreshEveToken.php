<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EVE Token 自动刷新中间件
 * 
 * 在每次请求时检查 Token 是否需要刷新，并自动刷新
 */
class AutoRefreshEveToken
{
    /**
     * 处理传入的请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // 只处理已登录且有 EVE 角色的用户
        if (!$user || !$user->eve_character_id || !$user->refresh_token) {
            return $next($request);
        }
        
        // 检查是否需要刷新 Token（过期或即将过期）
        if ($user->isTokenExpired() || $user->shouldRefreshToken()) {
            Log::info('🔄 [AutoRefreshToken] 检测到 Token 需要刷新', [
                'user_id' => $user->id,
                'character' => $user->name,
            ]);
            
            $this->refreshToken($user);
        }
        
        return $next($request);
    }
    
    /**
     * 刷新 Token
     */
    private function refreshToken($user)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post(
                config('esi.oauth_url') . 'token',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]
            );
            
            if ($response->ok()) {
                $tokenData = $response->json();
                
                $user->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                ]);
                
                Log::info('✅ [AutoRefreshToken] Token 刷新成功', [
                    'user_id' => $user->id,
                    'character' => $user->name,
                    'new_expires_at' => $user->getTokenExpiryFormatted(),
                ]);
            } else {
                Log::error('❌ [AutoRefreshToken] Token 刷新失败', [
                    'user_id' => $user->id,
                    'character' => $user->name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                // 如果刷新失败（可能是 Refresh Token 过期），需要重新授权
                if ($response->status() === 400 || $response->status() === 401) {
                    Log::warning('[AutoRefreshToken] Refresh Token 可能已过期，需要重新授权');
                    session()->flash('error', '授权已过期，请重新授权。');
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ [AutoRefreshToken] Token 刷新异常', [
                'user_id' => $user->id,
                'character' => $user->name,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
