<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BattleReport\BattleReportService;
use Illuminate\Http\Request;

/**
 * 战场报告控制器
 * 
 * 提供战场报告页面和API接口
 */
class BattleReportController extends Controller
{
    protected BattleReportService $service;

    public function __construct(BattleReportService $service)
    {
        $this->service = $service;
    }

    /**
     * 战场报告页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;

        return view('battlereport.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
        ]);
    }

    /**
     * 搜索星系KM并返回阵营预览
     * 
     * POST /api/battlereport/search
     */
    public function search(Request $request)
    {
        $request->validate([
            'system_id' => 'required|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'include_nearby' => 'boolean',
        ]);

        try {
            $result = $this->service->searchKills(
                (int) $request->input('system_id'),
                $request->input('start_time'),
                $request->input('end_time'),
                (bool) $request->input('include_nearby', false)
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 生成完整战场报告
     * 
     * POST /api/battlereport/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'system_id' => 'required|integer|min:1',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'include_nearby' => 'boolean',
            'red_factions' => 'required|array|min:1',
            'red_factions.*' => 'integer',
            'blue_factions' => 'required|array|min:1',
            'blue_factions.*' => 'integer',
        ]);

        try {
            $report = $this->service->generateReport(
                (int) $request->input('system_id'),
                $request->input('start_time'),
                $request->input('end_time'),
                (bool) $request->input('include_nearby', false),
                $request->input('red_factions', []),
                $request->input('blue_factions', [])
            );

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}