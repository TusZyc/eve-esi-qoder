<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KillmailService;
use Illuminate\Http\Request;

class KillmailController extends Controller
{
    protected $killmailService;
    
    public function __construct(KillmailService $killmailService)
    {
        $this->killmailService = $killmailService;
    }
    
    /**
     * 渲染 KM 查询页面
     */
    public function index(Request $request)
    {
        return view('killmails.index', [
            'user' => $request->user(),
        ]);
    }
    
    /**
     * 搜索角色
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'error' => 'query_too_short',
                'message' => '搜索关键词至少 2 个字符',
            ], 400);
        }
        
        try {
            $results = $this->killmailService->searchCharacter($query);
            
            return response()->json([
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'source' => 'kb-autocomplete+esi',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'search_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * 获取角色 KM 列表
     */
    public function pilotKills($pilotId, Request $request)
    {
        $mode = $request->input('mode', 'kills'); // kills, losses, all
        
        if (!is_numeric($pilotId) || $pilotId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_pilot_id',
                'message' => '无效的角色 ID',
            ], 400);
        }
        
        try {
            $kills = $this->killmailService->getPilotKills((int) $pilotId, $mode);
            
            return response()->json([
                'success' => true,
                'data' => $kills,
                'pilot_id' => $pilotId,
                'mode' => $mode,
                'count' => count($kills),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * 获取 KM 详情
     * 支持前端传入 hash 参数（前端从KB页面提取），直接走ESI
     */
    public function killDetail($killId, Request $request)
    {
        if (!is_numeric($killId) || $killId <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_kill_id',
                'message' => '无效的 KM ID',
            ], 400);
        }
        
        $hash = $request->input('hash');
        
        try {
            if (!empty($hash) && preg_match('/^[a-f0-9]{20,}$/i', $hash)) {
                // 前端已提供 hash，直接走 ESI
                $detail = $this->killmailService->getKillDetailsByHash((int) $killId, $hash);
            } else {
                // 无 hash，尝试后端获取（可能被KB反爬阻止）
                $detail = $this->killmailService->getKillDetails((int) $killId);
            }
            
            return response()->json([
                'success' => true,
                'data' => $detail,
                'kill_id' => $killId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'fetch_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
