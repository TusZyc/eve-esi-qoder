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
     * 显示授权引导页面
     */
    public function guide()
    {
        return view('auth.guide');
    }
    
    /**
     * 处理授权码提交
     */
    public function callback(Request $request)
    {
        Log::info('=== OAuth2 授权开始 ===');
        Log::info('请求参数：' . json_encode($request->all()));
        
        // 获取用户粘贴的 URL
        $callbackUrl = $request->query('callback_url');
        
        if (empty($callbackUrl)) {
            Log::error('缺少 callback_url 参数');
            return redirect()->route('auth.guide')
                ->with('error', '请提供授权后的完整 URL');
        }
        
        // 从 URL 中提取 code
        $code = $this->extractCodeFromUrl($callbackUrl);
        
        if (empty($code)) {
            Log::error('无法从 URL 中提取 code', ['url' => $callbackUrl]);
            return redirect()->route('auth.guide')
                ->with('error', '无法从 URL 中提取授权码（code）。请确保 URL 格式正确：https://.../oauth2-redirect.html?code=XXX&state=XXX');
        }
        
        Log::info('Code 提取成功：' . substr($code, 0, 20) . '...');
        
        // 用 code 换取 Token
        Log::info('正在换取 Token...');
        $tokenData = $this->getAccessToken($code);
        
        if (empty($tokenData['access_token'])) {
            Log::error('Token 换取失败：' . json_encode($tokenData));
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
            return redirect()->route('auth.guide')
                ->with('error', '授权码无效或已过期，请重新授权。错误信息：' . $errorMsg);
        }
        
        Log::info('✅ Token 换取成功');
        
        // 使用 Access Token 获取角色信息
        Log::info('正在获取角色信息...');
        $characterResponse = Http::withToken($tokenData['access_token'])
            ->get('https://login.evepc.163.com/oauth/verify');
        
        if ($characterResponse->failed()) {
            Log::error('获取角色信息失败：' . $characterResponse->body());
            return redirect()->route('auth.guide')
                ->with('error', '获取角色信息失败：' . $characterResponse->body());
        }
        
        $characterData = $characterResponse->json();
        Log::info('✅ 角色信息获取成功：' . $characterData['CharacterName'] . ' (ID: ' . $characterData['CharacterID'] . ')');
        
        // 查找或创建用户
        Log::info('正在查找或创建用户...');
        $user = User::firstOrCreate(
            ['eve_character_id' => $characterData['CharacterID']],
            [
                'name' => $characterData['CharacterName'],
                'email' => $characterData['CharacterName'] . '@eve.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]
        );
        Log::info('用户 ' . ($user->wasRecentlyCreated ? '已创建' : '已找到') . ', ID: ' . $user->id);
        
        // 更新 Token 信息
        Log::info('正在更新 Token 信息...');
        $user->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 1200),
            'corporation_id' => $characterData['CorporationID'] ?? null,
            'alliance_id' => $characterData['AllianceID'] ?? null,
        ]);
        
        Log::info('✅ 用户数据已保存');
        
        // 登录用户
        Log::info('正在登录用户...');
        Auth::login($user);
        Log::info('✅ 用户已登录，Auth ID: ' . Auth::id());
        
        // 重定向到仪表盘
        Log::info('正在重定向到仪表盘...');
        return redirect()->route('dashboard')
            ->with('success', '欢迎，' . $characterData['CharacterName'] . '! 授权成功！');
    }
    
    /**
     * 从回调 URL 中提取 code 参数
     */
    private function extractCodeFromUrl($url)
    {
        $queryParams = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
        return $queryParams['code'] ?? null;
    }
    
    /**
     * 用 authorization code 换取 Access Token
     */
    private function getAccessToken($code)
    {
        $tokenUrl = config('esi.oauth_url') . 'token';
        $clientId = config('esi.client_id');
        
        Log::info('请求 Token 端点：' . $tokenUrl);
        
        try {
            // 国服 OAuth2 需要在 body 中传递 client_id
            // 设置超时时间为 10 秒，避免服务器维护时卡住
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $clientId,
                ]);
            
            Log::info('Token 响应状态：' . $response->status());
            
            if ($response->failed()) {
                Log::error('Token 换取失败：' . $response->body());
                return [
                    'error' => 'Token request failed',
                    'error_description' => $response->body() ?: 'HTTP ' . $response->status(),
                ];
            }
            
            return $response->json();
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Token 请求连接失败：' . $e->getMessage());
            return [
                'error' => 'connection_error',
                'error_description' => '无法连接到 EVE 授权服务器，可能是服务器维护或网络问题。请稍后再试。',
            ];
        } catch (\Exception $e) {
            Log::error('Token 请求异常：' . $e->getMessage());
            return [
                'error' => 'unexpected_error',
                'error_description' => '发生未知错误：' . $e->getMessage(),
            ];
        }
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
}
