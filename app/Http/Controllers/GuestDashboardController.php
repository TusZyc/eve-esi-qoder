<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GuestDashboardController extends Controller
{
    /**
     * 显示游客仪表盘（无需授权）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // 只有用户有EVE角色ID时才算已授权
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('guest-dashboard', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
