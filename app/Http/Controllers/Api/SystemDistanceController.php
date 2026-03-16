<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemDistanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemDistanceController extends Controller
{
    /**
     * 星系距离服务
     *
     * @var SystemDistanceService
     */
    protected $distanceService;

    /**
     * 构造函数
     *
     * @param SystemDistanceService $distanceService
     */
    public function __construct(SystemDistanceService $distanceService)
    {
        $this->distanceService = $distanceService;
    }

    /**
     * 计算两个星系之间的最短跳跃路径
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pathDistance(Request $request)
    {
        $fromSystemId = $request->query('from', $request->from);
        $toSystemId = $request->query('to', $request->to);

        // 验证输入
        if (!$fromSystemId || !$toSystemId) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => '必须提供 from 和 to 参数',
            ], 400);
        }

        if (!is_numeric($fromSystemId) || !is_numeric($toSystemId)) {
            return response()->json([
                'error' => 'invalid_parameters',
                'message' => '星系 ID 必须是数字',
            ], 400);
        }

        $fromSystemId = (int) $fromSystemId;
        $toSystemId = (int) $toSystemId;

        // 检查星系是否存在
        if (!$this->distanceService->systemExists($fromSystemId)) {
            return response()->json([
                'error' => 'system_not_found',
                'message' => "起始星系 {$fromSystemId} 不存在",
            ], 404);
        }

        if (!$this->distanceService->systemExists($toSystemId)) {
            return response()->json([
                'error' => 'system_not_found',
                'message' => "目标星系 {$toSystemId} 不存在",
            ], 404);
        }

        // 获取路径
        list($distance, $path) = $this->distanceService->getPathDistance($fromSystemId, $toSystemId);

        if ($distance === null) {
            return response()->json([
                'error' => 'no_path_found',
                'message' => "无法找到从 {$fromSystemId} 到 {$toSystemId} 的路径",
                'from_system_id' => $fromSystemId,
                'to_system_id' => $toSystemId,
            ], 404);
        }

        // 获取路径详情
        $pathDetails = $this->distanceService->getPathDetails($path);

        return response()->json([
            'success' => true,
            'jump_count' => $distance,
            'path' => $path,
            'path_details' => $pathDetails,
            'from_system_id' => $fromSystemId,
            'to_system_id' => $toSystemId,
        ]);
    }

    /**
     * 获取两个星系之间的欧几里得距离
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function euclideanDistance(Request $request)
    {
        $fromSystemId = $request->query('from', $request->from);
        $toSystemId = $request->query('to', $request->to);

        if (!$fromSystemId || !$toSystemId) {
            return response()->json([
                'error' => 'missing_parameters',
                'message' => '必须提供 from 和 to 参数',
            ], 400);
        }

        if (!is_numeric($fromSystemId) || !is_numeric($toSystemId)) {
            return response()->json([
                'error' => 'invalid_parameters',
                'message' => '星系 ID 必须是数字',
            ], 400);
        }

        $fromSystemId = (int) $fromSystemId;
        $toSystemId = (int) $toSystemId;

        // 计算距离
        $distanceMeters = $this->distanceService->getEuclideanDistance($fromSystemId, $toSystemId);
        $distanceLy = $this->distanceService->getEuclideanDistanceLightYears($fromSystemId, $toSystemId);

        if ($distanceMeters === null) {
            return response()->json([
                'error' => 'coordinates_not_found',
                'message' => '无法获取星系坐标',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'from_system_id' => $fromSystemId,
            'to_system_id' => $toSystemId,
            'distance' => [
                'meters' => $distanceMeters,
                'light_years' => round($distanceLy, 6),
            ],
        ]);
    }

    /**
     * 获取星系名称
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function systemName(Request $request)
    {
        $systemId = $request->query('id', $request->id);
        $language = $request->query('language', 'zh');

        if (!$systemId) {
            return response()->json([
                'error' => 'missing_parameter',
                'message' => '必须提供 id 参数',
            ], 400);
        }

        if (!is_numeric($systemId)) {
            return response()->json([
                'error' => 'invalid_parameter',
                'message' => '星系 ID 必须是数字',
            ], 400);
        }

        $systemId = (int) $systemId;
        $name = $this->distanceService->getSystemName($systemId, $language);

        return response()->json([
            'success' => true,
            'system_id' => $systemId,
            'name' => $name,
            'language' => $language,
        ]);
    }

    /**
     * 批量获取星系信息
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchInfo(Request $request)
    {
        $systemIds = $request->query('ids', $request->ids);
        $language = $request->query('language', 'zh');

        if (!$systemIds) {
            return response()->json([
                'error' => 'missing_parameter',
                'message' => '必须提供 ids 参数(逗号分隔的 ID 列表)',
            ], 400);
        }

        $ids = is_array($systemIds) ? $systemIds : explode(',', $systemIds);
        $systemIds = array_filter(array_map('intval', $ids), 'is_numeric');

        if (empty($systemIds)) {
            return response()->json([
                'error' => 'invalid_parameters',
                'message' => '所有 ID 必须是数字',
            ], 400);
        }

        $result = [];
        foreach ($systemIds as $id) {
            $name = $this->distanceService->getSystemName($id, $language);
            $coords = $this->distanceService->getSystemCoordinates($id);
            
            $result[] = [
                'system_id' => $id,
                'name' => $name,
                'coordinates' => $coords,
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($result),
            'systems' => $result,
        ]);
    }
}
