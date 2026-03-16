<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketController extends Controller
{
    /**
     * 显示市场页面
     * 公开访问，但根据登录状态显示不同功能
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;

        // 获取热门区域配置
        $popularRegions = config('market.popular_regions', []);
        $defaultRegion = config('market.default_region', 10000002);

        return view('market.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'market',
            'popularRegions' => $popularRegions,
            'defaultRegion' => $defaultRegion,
        ]);
    }
}