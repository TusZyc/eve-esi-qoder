<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\WormholeService;

/**
 * 虫洞公开 API 控制器
 */
class WormholeApiController extends Controller
{
    private WormholeService $wormholeService;
    
    public function __construct(WormholeService $wormholeService)
    {
        $this->wormholeService = $wormholeService;
    }
    
    /**
     * 搜索虫洞星系
     * 
     * GET /api/public/wormhole/search?q=J100033
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min((int)$request->get('limit', 20), 100);
        
        if (strlen($query) < 1) {
            return response()->json(['results' => []]);
        }
        
        $results = $this->wormholeService->searchSystems($query, $limit);
        
        return response()->json(['results' => $results]);
    }
    
    /**
     * 自动补全
     * 
     * GET /api/public/wormhole/autocomplete?q=100
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 1) {
            return response()->json([]);
        }
        
        $results = $this->wormholeService->autocomplete($query, 10);
        
        return response()->json($results);
    }
    
    /**
     * 获取虫洞星系详情
     * 
     * GET /api/public/wormhole/system/{systemId}
     */
    public function systemInfo($systemId): JsonResponse
    {
        $systemInfo = $this->wormholeService->getSystemInfo($systemId);
        
        if (!$systemInfo) {
            return response()->json(['error' => '未找到该虫洞星系'], 404);
        }
        
        return response()->json($systemInfo);
    }
    
    /**
     * 获取虫洞星系击杀报告
     * 
     * GET /api/public/wormhole/system/{systemId}/kills
     */
    public function systemKills($systemId, Request $request): JsonResponse
    {
        $limit = min((int)$request->get('limit', 5), 20);
        
        $kills = $this->wormholeService->getSystemKills((int)$systemId, $limit);
        
        return response()->json($kills);
    }
    
    /**
     * 获取虫洞星系列表（分页）
     * 
     * GET /api/public/wormhole/systems?class=2&effect=pulsar&page=1
     */
    public function systemsList(Request $request): JsonResponse
    {
        $filters = [];
        
        if ($request->has('class')) {
            $filters['class'] = (int)$request->get('class');
        }
        
        if ($request->has('effect')) {
            $filters['effect'] = $request->get('effect');
        }
        
        $page = max(1, (int)$request->get('page', 1));
        $perPage = min(100, max(10, (int)$request->get('per_page', 50)));
        
        $result = $this->wormholeService->getSystemsList($filters, $page, $perPage);
        
        return response()->json($result);
    }
    
    /**
     * 获取虫洞类型列表
     * 
     * GET /api/public/wormhole/types?destination=high_sec
     */
    public function typesList(Request $request): JsonResponse
    {
        $destination = $request->get('destination');
        
        $types = $this->wormholeService->getTypesList($destination);
        
        return response()->json(['types' => $types]);
    }
    
    /**
     * 获取虫洞效果列表
     * 
     * GET /api/public/wormhole/effects
     */
    public function effectsList(): JsonResponse
    {
        $effects = WormholeService::getEffectsList();
        
        return response()->json(['effects' => $effects]);
    }
}