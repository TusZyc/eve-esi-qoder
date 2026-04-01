<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\WormholeService;

/**
 * 虫洞页面控制器
 */
class WormholeController extends Controller
{
    private WormholeService $wormholeService;
    
    public function __construct(WormholeService $wormholeService)
    {
        $this->wormholeService = $wormholeService;
    }
    
    /**
     * 虫洞查询主页
     */
    public function index(Request $request)
    {
        // 获取效果列表（用于筛选）
        $effects = WormholeService::getEffectsList();
        
        // 登录状态
        $user = Auth::user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('wormhole.index', [
            'systemInfo' => null,
            'kills' => null,
            'effects' => $effects,
            'searchQuery' => null,
            'searchError' => null,
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'wormhole',
        ]);
    }
    
    /**
     * 虫洞星系详情页
     */
    public function show(string $systemName)
    {
        try {
            $systemInfo = $this->wormholeService->getSystemInfo($systemName);
        } catch (\Exception $e) {
            $systemInfo = null;
        }
        
        if (!$systemInfo) {
            // 返回虫洞首页并带上错误提示，而非500/404白页
            $effects = WormholeService::getEffectsList();
            $user = Auth::user();
            $isLoggedIn = $user && $user->eve_character_id !== null;
            
            return view('wormhole.index', [
                'systemInfo' => null,
                'kills' => null,
                'effects' => $effects,
                'searchQuery' => $systemName,
                'searchError' => '未找到虫洞星系「' . e($systemName) . '」，请检查编号是否正确',
                'user' => $user,
                'isLoggedIn' => $isLoggedIn,
                'activePage' => 'wormhole',
            ]);
        }
        
        // 获取击杀报告（最多5条）
        $kills = $this->wormholeService->getSystemKills($systemInfo['system_id'], 5);
        
        // 获取效果列表
        $effects = WormholeService::getEffectsList();
        
        // 登录状态
        $user = Auth::user();
        $isLoggedIn = $user && $user->eve_character_id !== null;
        
        return view('wormhole.index', [
            'systemInfo' => $systemInfo,
            'kills' => $kills,
            'effects' => $effects,
            'searchQuery' => $systemName,
            'searchError' => null,
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'wormhole',
        ]);
    }
}