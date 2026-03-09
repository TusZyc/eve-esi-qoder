<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        
        Log::info('OAuth2 登录请求', ['authUrl' => $authUrl]);
        
        return redirect()->away($authUrl);
    }
    
    /**
     * 处理 OAuth2 回调
     */
    public function callback(Request $request)
    {
        Log::info('OAuth2 回调', ['query' => $request->all()]);
        
        // 验证 state
        if ($request->state !== session('esi_state')) {
            Log::error('State 验证失败', [
                'expected' => session('esi_state'),
                'received' => $request->state,
            ]);
            return redirect()->route('home')
                ->with('error', '授权验证失败，请重新尝试');
        }
        
        // 检查错误
        if ($request->has('error')) {
            Log::error('授权错误', ['error' => $request->error]);
            return redirect()->route('home')
                ->with('error', '授权被拒绝或发生错误：' . ($request->error_description ?? $request->error));
        }
        
        // 检查是否有 code
        if (!$request->has('code')) {
            Log::error('缺少授权码');
            return redirect()->route('home')
                ->with('error', '未收到授权码');
        }
        
        // 换取 Access Token
        Log::info('换取 Token', ['code' => $request->code]);
        
        $response = Http::withBasicAuth(
            config('esi.client_id'),
            config('esi.client_secret')
        )->post(config('esi.oauth_url') . 'token', [
            'grant_type' => 'authorization_code',
            'code' => $request->code,
            'redirect_uri' => config('esi.redirect_uri'),
        ]);
        
        if ($response->failed()) {
            Log::error('Token 换取失败', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return redirect()->route('home')
                ->with('error', 'Token 换取失败：' . $response->body());
        }
        
        $tokenData = $response->json();
        Log::info('Token 换取成功', ['expires_in' => $tokenData['expires_in'] ?? 'N/A']);
        
        // 使用 Access Token 获取角色信息
        $characterResponse = Http::withToken($tokenData['access_token'])
            ->get(config('esi.base_url') . 'verify/');
        
        if ($characterResponse->failed()) {
            Log::error('获取角色信息失败', [
                'status' => $characterResponse->status(),
                'body' => $characterResponse->body(),
            ]);
            return redirect()->route('home')
                ->with('error', '获取角色信息失败');
        }
        
        $characterData = $characterResponse->json();
        Log::info('角色信息', ['character' => $characterData]);
        
        // 查找或创建用户
        $user = User::firstOrCreate(
            ['eve_character_id' => $characterData['CharacterID']],
            [
                'name' => $characterData['CharacterName'],
                'email' => $characterData['CharacterName'] . '@eve.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]
        );
        
        // 更新 Token 信息
        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            'corporation_id' => $characterData['CorporationID'] ?? null,
            'alliance_id' => $characterData['AllianceID'] ?? null,
        ]);
        
        Log::info('用户登录成功', ['user_id' => $user->id, 'character_id' => $user->eve_character_id]);
        
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
        
        Log::info('用户登出', ['user_id' => Auth::id()]);
        
        return redirect()->route('home')
            ->with('success', '已安全登出');
    }
}
