<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * 显示仪表盘
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 检查 Token 是否过期
        if ($user->isTokenExpired()) {
            $this->refreshToken($user);
        }
        
        // 获取服务器状态
        $statusUrl = config('esi.base_url') . 'status/';
        $statusResponse = Http::get($statusUrl);
        $serverStatus = $statusResponse->ok() ? $statusResponse->json() : null;
        
        // 获取角色技能信息
        $skillsData = null;
        if ($user->eve_character_id) {
            $skillsResponse = Http::withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skills/');
            
            if ($skillsResponse->ok()) {
                $skillsData = $skillsResponse->json();
            }
        }
        
        // 获取技能队列
        $skillQueue = null;
        if ($user->eve_character_id) {
            $queueResponse = Http::withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skillqueue/');
            
            if ($queueResponse->ok()) {
                $skillQueue = $queueResponse->json();
            }
        }
        
        return view('dashboard', [
            'user' => $user,
            'serverStatus' => $serverStatus,
            'skillsData' => $skillsData,
            'skillQueue' => $skillQueue,
        ]);
    }
    
    /**
     * 刷新 Token
     */
    private function refreshToken(User $user)
    {
        $response = Http::withBasicAuth(
            config('esi.client_id'),
            config('esi.client_secret')
        )->post(config('esi.oauth_url') . 'token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token,
        ]);
        
        if ($response->ok()) {
            $tokenData = $response->json();
            $user->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);
        }
    }
}
