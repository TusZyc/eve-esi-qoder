<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureSiteAdmin
{
    /**
     * 检查用户是否是站点管理员
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 检查是否已登录
        if (!Auth::check()) {
            return redirect()->route('auth.guide');
        }

        $user = Auth::user();
        
        // 检查是否有 EVE 角色
        if (!$user->eve_character_id) {
            return redirect()->route('auth.guide');
        }

        // 检查是否是站点管理员
        $siteAdmins = config('admin.site_admins', []);
        
        if (!in_array($user->name, $siteAdmins)) {
            // 非管理员返回 403
            abort(403, '无权访问站点管理后台');
        }

        return $next($request);
    }

    /**
     * 静态方法：检查指定用户是否是站点管理员
     */
    public static function isSiteAdmin($user): bool
    {
        if (!$user || !$user->name) {
            return false;
        }

        $siteAdmins = config('admin.site_admins', []);
        return in_array($user->name, $siteAdmins);
    }
}
