<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\EveHelper;
use App\Services\StationNameService;

/**
 * 角色位置 API 控制器
 */
class CharacterLocationController extends Controller
{
    private StationNameService $stationNameService;
    
    public function __construct(StationNameService $stationNameService)
    {
        $this->stationNameService = $stationNameService;
    }
    /**
     * 获取角色当前位置
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        Log::info('📍 [API] 请求角色位置', [
            'user_id' => $user->id ?? 'null',
            'character_id' => $user->eve_character_id ?? 'null',
        ]);
        
        if (!$user || !$user->eve_character_id) {
            return response()->json([
                'success' => false,
                'error' => 'unauthorized',
                'message' => '未授权，请重新登录',
            ], 401);
        }
        
        if (empty($user->access_token)) {
            return response()->json([
                'success' => false,
                'error' => 'no_token',
                'message' => '缺少访问令牌',
            ], 401);
        }
        
        try {
            // 获取角色位置
            $locationResponse = Http::timeout(10)
                ->withToken($user->access_token)
                ->get(config('esi.base_url') . "characters/{$user->eve_character_id}/location/");
            
            if ($locationResponse->failed()) {
                Log::error('📍 [API] 位置信息获取失败', [
                    'status' => $locationResponse->status(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'eve_api_error',
                    'message' => 'EVE API 错误',
                ], $locationResponse->status());
            }
            
            $location = $locationResponse->json();
            
            // 位置信息
            $solarSystemId = $location['solar_system_id'] ?? null;
            $stationId = $location['station_id'] ?? null;
            $structureId = $location['structure_id'] ?? null;
            
            Log::info('📍 [API] 位置信息', [
                'solar_system_id' => $solarSystemId,
                'station_id' => $stationId,
                'structure_id' => $structureId,
            ]);
            
            // 查询星系名称（使用 language=zh 获取中文）
            $solarSystemName = '未知星系';
            if ($solarSystemId) {
                try {
                    $sysResponse = Http::timeout(10)
                        ->get(config('esi.base_url') . "universe/systems/{$solarSystemId}/", [
                            'datasource' => 'serenity',
                            'language' => 'zh'
                        ]);
                    if ($sysResponse->ok()) {
                        $solarSystemName = $sysResponse->json()['name'] ?? '未知星系';
                    }
                } catch (\Exception $e) {
                    Log::warning('📍 [API] 星系名称查询失败：' . $e->getMessage());
                }
            }
            
            // 构建位置显示文本
            $locationDisplay = '';
            
            if ($stationId) {
                // 在 NPC 空间站 - 使用 StationNameService 获取中文名
                $stationName = $this->stationNameService->getStationNameZh($stationId);
                $locationDisplay = "{$solarSystemName} - {$stationName}";
            } elseif ($structureId) {
                // 在玩家建筑 - 使用 StationNameService 获取名称
                $structureName = $this->stationNameService->getStructureName($structureId, $user->access_token);
                $locationDisplay = "{$solarSystemName} - {$structureName}";
            } else {
                // 在太空中（未停靠）
                $locationDisplay = "{$solarSystemName} - 未停靠";
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'solar_system_id' => $solarSystemId,
                    'solar_system_name' => $solarSystemName,
                    'station_id' => $stationId,
                    'station_name' => $stationId ? $this->stationNameService->getStationNameZh($stationId) : null,
                    'structure_id' => $structureId,
                    'structure_name' => $structureId ? $this->stationNameService->getStructureName($structureId, $user->access_token) : null,
                    'location_display' => $locationDisplay,
                    'is_docked' => $stationId !== null || $structureId !== null,
                ],
            ]);
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('📍 [API] 位置信息连接失败：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'connection_timeout',
                'message' => '连接超时',
            ], 503);
        } catch (\Exception $e) {
            Log::error('📍 [API] 位置信息请求异常：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'unknown_error',
                'message' => '未知错误',
            ], 500);
        }
    }
}
