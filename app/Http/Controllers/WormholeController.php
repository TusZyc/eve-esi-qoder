<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
        $tab = $request->get('tab', 'search');
        $systemName = $request->get('system');
        
        // 如果指定了系统，获取详情
        $systemInfo = null;
        if ($systemName) {
            $systemInfo = $this->wormholeService->getSystemInfo($systemName);
        }
        
        // 获取效果列表（用于筛选）
        $effects = WormholeService::getEffectsList();
        
        return view('wormhole.index', [
            'tab' => $tab,
            'systemInfo' => $systemInfo,
            'effects' => $effects,
            'searchQuery' => $systemName,
        ]);
    }
    
    /**
     * 虫洞星系详情页
     */
    public function show(string $systemName)
    {
        $systemInfo = $this->wormholeService->getSystemInfo($systemName);
        
        if (!$systemInfo) {
            abort(404, '未找到该虫洞星系');
        }
        
        // 获取击杀报告
        $kills = $this->wormholeService->getSystemKills($systemInfo['system_id']);
        
        // 获取效果列表
        $effects = WormholeService::getEffectsList();
        
        return view('wormhole.index', [
            'tab' => 'search',
            'systemInfo' => $systemInfo,
            'kills' => $kills,
            'effects' => $effects,
            'searchQuery' => $systemName,
        ]);
    }
}