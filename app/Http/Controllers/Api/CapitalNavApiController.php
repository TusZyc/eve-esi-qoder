<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CapitalNavigationService;
use App\Services\EveDataService;
use Illuminate\Http\Request;

class CapitalNavApiController extends Controller
{
    protected CapitalNavigationService $navService;
    protected EveDataService $eveDataService;

    public function __construct(CapitalNavigationService $navService, EveDataService $eveDataService)
    {
        $this->navService = $navService;
        $this->eveDataService = $eveDataService;
    }

    /**
     * 星系名称自动补全
     */
    public function systemAutocomplete(Request $request)
    {
        $query = $request->input('q', '');
        if (mb_strlen($query) < 1) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $results = $this->eveDataService->searchByName($query, 'system', 15);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * 计算两星系间的欧几里得距离
     */
    public function distance(Request $request)
    {
        $fromId = (int) $request->input('from');
        $toId = (int) $request->input('to');

        if (!$fromId || !$toId) {
            return response()->json([
                'success' => false,
                'message' => '必须提供 from 和 to 参数（星系 ID）',
            ], 400);
        }

        try {
            $result = $this->navService->getDistance($fromId, $toId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => '星系不存在或数据缺失',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 获取一跳可达星系列表
     */
    public function reachableSystems(Request $request)
    {
        $originId = (int) $request->input('origin');
        $shipType = $request->input('ship_type', '');
        $jdcLevel = (int) $request->input('jdc_level', 0);
        $fuelEffLevel = (int) $request->input('fuel_eff_level', 0);
        $jfLevel = (int) $request->input('jf_level', 0);

        // 验证
        if (!$originId) {
            return response()->json(['success' => false, 'message' => '必须提供起始星系 ID'], 400);
        }
        if (!isset(CapitalNavigationService::SHIP_DATA[$shipType])) {
            return response()->json(['success' => false, 'message' => '无效的舰船类型'], 400);
        }
        if ($jdcLevel < 0 || $jdcLevel > 5 || $fuelEffLevel < 0 || $fuelEffLevel > 5 || $jfLevel < 0 || $jfLevel > 5) {
            return response()->json(['success' => false, 'message' => '技能等级必须在 0-5 之间'], 400);
        }

        try {
            $result = $this->navService->getReachableSystems($originId, $shipType, $jdcLevel, $fuelEffLevel, $jfLevel);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 规划旗舰路线
     */
    public function planRoute(Request $request)
    {
        $fromId = (int) $request->input('from');
        $toId = (int) $request->input('to');
        $shipType = $request->input('ship_type', '');
        $jdcLevel = (int) $request->input('jdc_level', 0);
        $fuelEffLevel = (int) $request->input('fuel_eff_level', 0);
        $jfLevel = (int) $request->input('jf_level', 0);
        $useStargates = filter_var($request->input('use_stargates', false), FILTER_VALIDATE_BOOLEAN);

        // 验证
        if (!$fromId || !$toId) {
            return response()->json(['success' => false, 'message' => '必须提供起始和目标星系 ID'], 400);
        }
        if (!isset(CapitalNavigationService::SHIP_DATA[$shipType])) {
            return response()->json(['success' => false, 'message' => '无效的舰船类型'], 400);
        }
        if ($jdcLevel < 0 || $jdcLevel > 5 || $fuelEffLevel < 0 || $fuelEffLevel > 5 || $jfLevel < 0 || $jfLevel > 5) {
            return response()->json(['success' => false, 'message' => '技能等级必须在 0-5 之间'], 400);
        }

        try {
            $result = $this->navService->planRoute($fromId, $toId, $shipType, $jdcLevel, $fuelEffLevel, $jfLevel, $useStargates);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
