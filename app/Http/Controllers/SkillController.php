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
        return view('skills.index', [
            'user' => $request->user(),
        ]);
    }
}
