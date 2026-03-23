<?php

namespace App\Http\Controllers;

use App\Models\ScoutResult;
use App\Services\ScoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScoutController extends Controller
{
    private ScoutService $scoutService;
    
    public function __construct(ScoutService $scoutService)
    {
        $this->scoutService = $scoutService;
    }
    
    /**
     * 斥候工具首页（输入页面）
     * GET /scout
     */
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $isLoggedIn = $user !== null;
        $activePage = 'scout';
        
        $retentionOptions = ScoutService::getRetentionOptions();
        $defaultRetention = ScoutService::DEFAULT_RETENTION;
        
        return view('scout.index', compact('retentionOptions', 'defaultRetention', 'user', 'isLoggedIn', 'activePage'));
    }
    
    /**
     * 处理扫描数据提交
     * POST /scout
     */
    public function store(Request $request)
    {
        $request->validate([
            'clipboard_data' => 'required|string|min:1',
            'retention' => 'nullable|integer|in:0,2,12,48,168',
        ]);
        
        $clipboardText = $request->input('clipboard_data');
        $retentionHours = $request->input('retention', ScoutService::DEFAULT_RETENTION);
        
        // 生成 IP 哈希（用于防滥用）
        $ipHash = ScoutService::generateIpHash($request->ip());
        
        try {
            $result = $this->scoutService->process($clipboardText, $retentionHours, $ipHash);
            
            return redirect()->route('scout.result', ['id' => $result->id]);
            
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['error' => '处理失败：' . $e->getMessage()]);
        }
    }
    
    /**
     * 显示扫描结果
     * GET /scout/{id}
     */
    public function result(string $id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $isLoggedIn = $user !== null;
        $activePage = 'scout';
        
        $result = ScoutResult::find($id);
        
        if (!$result) {
            abort(404, '扫描结果不存在');
        }
        
        // 检查是否过期
        if ($result->isExpired()) {
            abort(410, '扫描结果已过期');
        }
        
        $items = $result->items ?? [];
        $statistics = $result->statistics ?? [];
        
        return view('scout.result', compact('result', 'items', 'statistics', 'user', 'isLoggedIn', 'activePage'));
    }
    
    /**
     * API: 获取扫描结果（JSON）
     * GET /api/scout/{id}
     */
    public function apiShow(string $id)
    {
        $result = ScoutResult::find($id);
        
        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => '扫描结果不存在',
            ], 404);
        }
        
        if ($result->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => '扫描结果已过期',
            ], 410);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $result->id,
                'items' => $result->items,
                'statistics' => $result->statistics,
                'share_url' => $result->getShareUrl(),
                'created_at' => $result->getCreatedAtFormatted(),
                'expires_at' => $result->getExpiresAtFormatted(),
            ],
        ]);
    }
}