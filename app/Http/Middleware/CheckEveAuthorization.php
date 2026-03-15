<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckEveAuthorization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // 检查用户是否已授权（有EVE角色信息）
        if (!Auth::check() || !Auth::user()->eve_character_id) {
            return redirect()->route('guest.dashboard');
        }

        return $next($request);
    }
}
