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
    public static function refreshIfNeeded(User $user): bool
    {
        if (!$user->eve_character_id || !$user->refresh_token) {
            return false;
        }

        if (!$user->isTokenExpired() && !$user->shouldRefreshToken()) {
            return true;
        }

        return self::refresh($user);
    }

    /**
     * 执行 Token 刷新
     */
    public static function refresh(User $user): bool
    {
        if (empty($user->refresh_token)) {
            Log::warning('[TokenRefresh] Refresh Token 为空', ['user_id' => $user->id]);
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->asForm()
                ->post(config('esi.oauth_url') . 'token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]);

            if ($response->ok()) {
                $data = $response->json();

                $user->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $user->refresh_token,
                    'token_expires_at' => now()->addSeconds($data['expires_in']),
                ]);

                Log::debug('[TokenRefresh] Token 刷新成功', [
                    'user_id' => $user->id,
                    'character' => $user->name,
                ]);
                return true;
            }

            Log::error('[TokenRefresh] Token 刷新失败', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            if ($response->status() === 400 || $response->status() === 401) {
                session()->flash('error', '授权已过期，请重新授权。');
            }

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('[TokenRefresh] 连接超时', ['user_id' => $user->id]);
            return false;
        } catch (\Exception $e) {
            Log::error('[TokenRefresh] 异常', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
