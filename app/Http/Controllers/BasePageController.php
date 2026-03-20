<?php

namespace App\Http\Controllers;

/**
 * 页面控制器基类
 * 提供通用的页面渲染功能，自动注入用户和登录状态
 */
class BasePageController extends Controller
{
    /**
     * 渲染页面视图
     *
     * @param string $viewName 视图名称
     * @param array $extra 额外的视图变量
     * @return \Illuminate\View\View
     */
    protected function renderPage(string $viewName, array $extra = [])
    {
        $user = request()->user();
        return view($viewName, array_merge([
            'user' => $user,
            'isLoggedIn' => $user && $user->eve_character_id !== null,
        ], $extra));
    }
}
