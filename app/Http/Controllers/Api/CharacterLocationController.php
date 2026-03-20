<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Services\EveDataService;

/**
 * 角色位置 API 控制器
 */
class CharacterLocationController extends Controller
{
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
            
            // 获取星系中文名
            $solarSystemName = '未知星系';
            if ($solarSystemId) {
                $solarSystemName = $this->getSystemName($solarSystemId);
            }
            
            // 构建位置显示文本
            $locationDisplay = '';
            $stationName = null;
            $structureName = null;
            
            if ($stationId) {
                // 在 NPC 空间站 - 先查本地数据，再调用 ESI
                $stationName = $this->getStationName($stationId);
                if ($stationName) {
                    $locationDisplay = $stationName;
                } else {
                    $locationDisplay = "{$solarSystemName} - 空间站 #{$stationId}";
                }
            } elseif ($structureId) {
                // 在玩家建筑 - 先查本地数据，再调用 ESI（需要 token）
                $structureName = $this->getStructureName($structureId, $user->access_token);
                if ($structureName) {
                    $locationDisplay = "{$solarSystemName} - {$structureName}";
                } else {
                    $locationDisplay = "{$solarSystemName} - 玩家建筑";
                }
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
                    'station_name' => $stationName,
                    'structure_id' => $structureId,
                    'structure_name' => $structureName,
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
    
    /**
     * 获取星系中文名
     * 优先从本地数据获取
     */
    private function getSystemName(int $systemId): string
    {
        // 1. 优先从本地数据获取
        $localInfo = EveDataService::getLocalSystemInfo($systemId);
        if ($localInfo && isset($localInfo['name'])) {
            return $localInfo['name'];
        }
        
        // 2. 从缓存获取
        $cacheKey = "eve_sysname_zh_{$systemId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 调用 ESI API
        try {
            $response = Http::timeout(10)
                ->get(config('esi.base_url') . "universe/systems/{$systemId}/", [
                    'datasource' => 'serenity',
                    'language' => 'zh',
                ]);
            
            if ($response->ok()) {
                $name = $response->json()['name'] ?? '未知星系';
                Cache::put($cacheKey, $name, 86400);
                return $name;
            }
        } catch (\Exception $e) {
            Log::warning("📍 [API] 获取星系名失败: {$systemId}", ['error' => $e->getMessage()]);
        }
        
        return '未知星系';
    }
    
    /**
     * 获取 NPC 空间站中文名
     * 优先从本地数据获取
     */
    private function getStationName(int $stationId): ?string
    {
        // 1. 优先从本地数据获取
        $localInfo = EveDataService::getLocalStationInfo($stationId);
        if ($localInfo && isset($localInfo['name'])) {
            return $localInfo['name'];
        }
        
        // 2. 从缓存获取
        $cacheKey = "eve_station_name_zh_{$stationId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 调用 ESI API (stations 接口是公开接口，不需要 token)
        try {
            $response = Http::timeout(10)
                ->get(config('esi.base_url') . "universe/stations/{$stationId}/", [
                    'datasource' => 'serenity',
                ]);
            
            if ($response->ok()) {
                $name = $response->json()['name'] ?? null;
                if ($name) {
                    Cache::put($cacheKey, $name, 86400);
                    return $name;
                }
            }
        } catch (\Exception $e) {
            Log::warning("📍 [API] 获取空间站名失败: {$stationId}", ['error' => $e->getMessage()]);
        }
        
        return null;
    }
    
    /**
     * 获取玩家建筑名称
     * 优先从本地数据获取（需要 token 访问 ESI）
     */
    private function getStructureName(int $structureId, string $token): ?string
    {
        // 1. 优先从本地数据获取
        $localInfo = EveDataService::getLocalStructureInfo($structureId);
        if ($localInfo && isset($localInfo['name'])) {
            return $localInfo['name'];
        }
        
        // 2. 从缓存获取
        $cacheKey = "eve_structure_name_{$structureId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 调用 ESI API (需要 token)
        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->get(config('esi.base_url') . "universe/structures/{$structureId}/", [
                    'datasource' => 'serenity',
                ]);
            
            if ($response->ok()) {
                $name = $response->json()['name'] ?? null;
                if ($name) {
                    Cache::put($cacheKey, $name, 3600); // 玩家建筑名缓存1小时
                    return $name;
                }
            }
        } catch (\Exception $e) {
            Log::warning("📍 [API] 获取建筑名失败: {$structureId}", ['error' => $e->getMessage()]);
        }
        
        return null;
    }
}
