<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Character;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * 重定向到 EVE OAuth2 授权页面
     */
    public function login()
    {
        $clientId = config('esi.client_id');
        $redirectUri = config('esi.redirect_uri');
        $state = bin2hex(random_bytes(16));
        
        // 保存 state 到 session 用于验证回调
        session(['esi_state' => $state]);
        
        $params = [
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'scope' => implode(' ', config('esi.scopes')),
            'state' => $state,
        ];
        
        $authUrl = config('esi.oauth_url') . 'authorize?' . http_build_query($params);
        
        return redirect()->away($authUrl);
    }
    
    /**
     * 处理 OAuth2 回调
     */
    public function callback(Request $request)
    {
        // 验证 state
        if ($request->state !== session('esi_state')) {
            return redirect()->route('home')
                ->with('error', '授权验证失败，请重新尝试');
        }
        
        // 检查错误
        if ($request->has('error')) {
            return redirect()->route('home')
                ->with('error', '授权被拒绝或发生错误');
        }
        
        // 换取 Access Token
        $response = Http::withBasicAuth(
            config('esi.client_id'),
            config('esi.client_secret')
        )->post(config('esi.oauth_url') . 'token', [
            'grant_type' => 'authorization_code',
            'code' => $request->code,
            'redirect_uri' => config('esi.redirect_uri'),
        ]);
        
        if ($response->failed()) {
            return redirect()->route('home')
                ->with('error', 'Token 换取失败：' . $response->body());
        }
        
        $tokenData = $response->json();
        
        // 使用 Access Token 获取角色信息
        $characterResponse = Http::withToken($tokenData['access_token'])
            ->get(config('esi.base_url') . 'verify/');
        
        if ($characterResponse->failed()) {
            return redirect()->route('home')
                ->with('error', '获取角色信息失败');
        }
        
        $characterData = $characterResponse->json();
        
        // 查找或创建用户
        $user = User::firstOrCreate(
            ['eve_character_id' => $characterData['CharacterID']],
            [
                'name' => $characterData['CharacterName'],
                'email' => $characterData['CharacterName'] . '@eve.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]
        );
        
        // 更新或创建角色信息
        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
        ]);
        
        // 同步角色详细信息
        $this->syncCharacterData($user, $tokenData['access_token']);
        
        // 登录用户
        Auth::login($user);
        
        return redirect()->route('dashboard')
            ->with('success', '欢迎，' . $characterData['CharacterName'] . '!');
    }
    
    /**
     * 登出
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('home')
            ->with('success', '已安全登出');
    }
    
    /**
     * 同步角色数据
     */
    private function syncCharacterData(User $user, string $accessToken)
    {
        // 获取角色详细信息
        $response = Http::withToken($accessToken)
            ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/');
        
        if ($response->ok()) {
            $data = $response->json();
            $user->update([
                'corporation_id' => $data['corporation_id'] ?? null,
                'alliance_id' => $data['alliance_id'] ?? null,
            ]);
        }
    }
}
