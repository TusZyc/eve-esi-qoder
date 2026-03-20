<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EVE Token 自动刷新中间件
 * 
 * 在每次请求时检查 Token 是否需要刷新，并自动刷新
 * 如果 Token 刷新失败且已过期，则阻止请求继续
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
                'is_expired' => $user->isTokenExpired(),
            ]);
            
            $refreshSuccess = $this->refreshToken($user, $request);
            
            if (!$refreshSuccess) {
                // 刷新失败，检查 Token 是否已过期
                // 重新获取用户数据（可能在 refreshToken 中被清除）
                $user->refresh();
                
                if ($user->isTokenExpired()) {
                    // Token 已过期且刷新失败 → 必须阻止请求
                    Log::warning('🚫 [AutoRefreshToken] Token 已过期且刷新失败，阻止请求', [
                        'user_id' => $user->id,
                        'character' => $user->name,
                        'path' => $request->path(),
                    ]);
                    
                    return $this->handleExpiredToken($request);
                }
                
                // Token 未过期但刷新失败 → 记录警告，继续使用当前 Token
                Log::warning('⚠️ [AutoRefreshToken] Token 刷新失败但尚未过期，继续使用当前 Token', [
                    'user_id' => $user->id,
                    'character' => $user->name,
                    'remaining_seconds' => $user->getTokenRemainingSeconds(),
                ]);
            }
        }
        
        return $next($request);
    }
    
    /**
     * 刷新 Token
     * 
     * @return bool 成功返回 true，失败返回 false
     */
    private function refreshToken($user, Request $request): bool
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->asForm()
                ->post(config('esi.oauth_url') . 'token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]);
            
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
                    'new_expires_at' => $user->fresh()->getTokenExpiryFormatted(),
                ]);
                
                return true;
            }
            
            // 刷新失败
            Log::error('❌ [AutoRefreshToken] Token 刷新失败', [
                'user_id' => $user->id,
                'character' => $user->name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            // 如果是 400/401，说明 Refresh Token 已失效，清除用户 token 信息
            if ($response->status() === 400 || $response->status() === 401) {
                Log::warning('🔒 [AutoRefreshToken] Refresh Token 已失效，清除 Token 信息', [
                    'user_id' => $user->id,
                    'character' => $user->name,
                ]);
                
                $user->update([
                    'access_token' => null,
                    'refresh_token' => null,
                    'token_expires_at' => null,
                ]);
            }
            
            return false;
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('⚠️ [AutoRefreshToken] Token 刷新连接超时', [
                'user_id' => $user->id,
                'character' => $user->name,
                'exception' => $e->getMessage(),
            ]);
            return false;
            
        } catch (\Exception $e) {
            Log::error('❌ [AutoRefreshToken] Token 刷新异常', [
                'user_id' => $user->id,
                'character' => $user->name,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * 处理 Token 过期的情况
     * 
     * - API 请求：返回 JSON 错误响应
     * - 页面请求：重定向到首页并带上错误消息
     */
    private function handleExpiredToken(Request $request): Response
    {
        // 判断是否是 API 请求
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'token_expired',
                'message' => 'EVE 授权已过期，请重新登录授权。',
            ], 401);
        }
        
        // 页面请求：强制登出并重定向到首页
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/')
            ->with('error', 'EVE 授权已过期，请重新登录授权。');
    }
}
