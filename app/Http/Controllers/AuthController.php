<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function guide()
    {
        return view('auth.guide');
    }

    public function callback(Request )
    {
         = ->query('callback_url');

        if (empty()) {
            return redirect()->route('auth.guide')
                ->with('error', '请提供授权后的完整 URL');
        }

        // 输入验证：检查 URL 格式
        if (!filter_var(, FILTER_VALIDATE_URL)) {
            return redirect()->route('auth.guide')
                ->with('error', 'URL 格式不正确，请粘贴完整的回调地址');
        }

         = ->extractCodeFromUrl();

        if (empty() || strlen() < 10 || strlen() > 2000) {
            return redirect()->route('auth.guide')
                ->with('error', '无法从 URL 中提取有效的授权码（code）');
        }

         = ->getAccessToken();

        if (empty(['access_token'])) {
             = ['error_description'] ?? ['error'] ?? '未知错误';
            return redirect()->route('auth.guide')
                ->with('error', '授权码无效或已过期，请重新授权。错误信息：' . );
        }

         = Http::timeout(10)
            ->withToken(['access_token'])
            ->get('https://login.evepc.163.com/oauth/verify');

        if (->failed()) {
            return redirect()->route('auth.guide')
                ->with('error', '获取角色信息失败');
        }

         = ->json();

        if (empty(['CharacterID']) || empty(['CharacterName'])) {
            return redirect()->route('auth.guide')
                ->with('error', '角色信息不完整，请重新授权');
        }

         = User::firstOrCreate(
            ['eve_character_id' => ['CharacterID']],
            [
                'name' => ['CharacterName'],
                'email' => ['CharacterName'] . '@eve.local',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]
        );

        ->update([
            'access_token' => ['access_token'],
            'refresh_token' => ['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds(['expires_in'] ?? 1200),
            'corporation_id' => ['CorporationID'] ?? null,
            'alliance_id' => ['AllianceID'] ?? null,
        ]);

        Auth::login();

        Log::info('[Auth] 用户登录成功', [
            'user_id' => ->id,
            'character' => ['CharacterName'],
        ]);

        return redirect()->route('dashboard')
            ->with('success', '欢迎，' . ['CharacterName'] . '! 授权成功！');
    }

    private function extractCodeFromUrl()
    {
         = [];
        parse_str(parse_url(, PHP_URL_QUERY) ?? '', );
        return ['code'] ?? null;
    }

    private function getAccessToken()
    {
        try {
             = Http::timeout(10)
                ->asForm()
                ->post(config('esi.oauth_url') . 'token', [
                    'grant_type' => 'authorization_code',
                    'code' => ,
                    'client_id' => config('esi.client_id'),
                ]);

            if (->failed()) {
                return [
                    'error' => 'Token request failed',
                    'error_description' => ->body() ?: 'HTTP ' . ->status(),
                ];
            }

            return ->json();
        } catch (\Illuminate\Http\Client\ConnectionException ) {
            return [
                'error' => 'connection_error',
                'error_description' => '无法连接到 EVE 授权服务器，请稍后再试。',
            ];
        } catch (\Exception ) {
            return [
                'error' => 'unexpected_error',
                'error_description' => '发生未知错误：' . ->getMessage(),
            ];
        }
    }

    public function logout(Request )
    {
        Auth::logout();
        ->session()->invalidate();
        ->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', '已安全登出');
    }
}
