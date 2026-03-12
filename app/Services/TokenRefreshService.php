<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenRefreshService
{
    /**
     * 检查并刷新 Token（如果需要）
     */
    public static function refreshIfNeeded(User ): bool
    {
        if (!->eve_character_id || !->refresh_token) {
            return false;
        }

        if (!->isTokenExpired() && !->shouldRefreshToken()) {
            return true;
        }

        return self::refresh();
    }

    /**
     * 执行 Token 刷新
     */
    public static function refresh(User ): bool
    {
        if (empty(->refresh_token)) {
            Log::warning('[TokenRefresh] Refresh Token 为空', ['user_id' => ->id]);
            return false;
        }

        try {
             = Http::timeout(10)
                ->asForm()
                ->post(config('esi.oauth_url') . 'token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => ->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]);

            if (->ok()) {
                 = ->json();

                ->update([
                    'access_token' => ['access_token'],
                    'refresh_token' => ['refresh_token'] ?? ->refresh_token,
                    'token_expires_at' => now()->addSeconds(['expires_in']),
                ]);

                Log::debug('[TokenRefresh] Token 刷新成功', [
                    'user_id' => ->id,
                    'character' => ->name,
                ]);
                return true;
            }

            Log::error('[TokenRefresh] Token 刷新失败', [
                'user_id' => ->id,
                'status' => ->status(),
            ]);

            if (->status() === 400 || ->status() === 401) {
                session()->flash('error', '授权已过期，请重新授权。');
            }

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException ) {
            Log::warning('[TokenRefresh] 连接超时', ['user_id' => ->id]);
            return false;
        } catch (\Exception ) {
            Log::error('[TokenRefresh] 异常', ['user_id' => ->id, 'error' => ->getMessage()]);
            return false;
        }
    }
}
