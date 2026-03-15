<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * 显示技能队列页面（仅渲染空壳，数据由前端异步加载）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // 只有用户有EVE角色ID时才算已授权
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('skills.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }
}
