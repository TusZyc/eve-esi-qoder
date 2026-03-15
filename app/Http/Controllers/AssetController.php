<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * 资产控制器
 */
class AssetController extends Controller
{
    /**
     * 显示资产列表页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // 只有用户有EVE角色ID时才算已授权
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('assets.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'pageTitle' => '我的资产',
        ]);
    }
}
