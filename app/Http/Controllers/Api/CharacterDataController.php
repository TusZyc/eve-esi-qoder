<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CharacterDataService;
use App\Services\EveDataService;
use Carbon\Carbon;

/**
 * 角色详细数据 API 控制器
 * 
 * 提供角色属性、植入体、克隆体、雇佣历史等数据的 API 端点
 */
class CharacterDataController extends Controller
{
    /**
     * EVE 数据服务实例
     */
    private EveDataService $eveDataService;

    public function __construct(EveDataService $eveDataService)
    {
        $this->eveDataService = $eveDataService;
    }

    /**
     * 获取角色属性
     * GET /api/dashboard/character/attributes
     */
    public function attributes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->access_token) {
                return response()->json([
                    'success' => false,
                    'message' => '未授权访问',
                ], 401);
            }

            $data = CharacterDataService::getSingleData(
                (string) $user->eve_character_id,
                $user->access_token,
                'attributes'
            );

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => '获取角色属性失败',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('[CharacterDataController] attributes 异常: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '服务器错误',
            ], 500);
        }
    }

    /**
     * 获取当前植入体
     * GET /api/dashboard/character/implants
     */
    public function implants(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->access_token) {
                return response()->json([
                    'success' => false,
                    'message' => '未授权访问',
                ], 401);
            }

            $data = CharacterDataService::getSingleData(
                (string) $user->eve_character_id,
                $user->access_token,
                'implants'
            );

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => '获取植入体失败',
                ]);
            }

            // 植入体数据是一个 type_id 数组，需要解析为名称
            $implantIds = is_array($data) ? $data : [];
            
            if (empty($implantIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            // 批量获取植入体名称
            $names = $this->eveDataService->getNamesByIds($implantIds);
            
            $result = [];
            foreach ($implantIds as $id) {
                $result[] = [
                    'id' => $id,
                    'name' => $names[$id] ?? '未知植入体 (ID: ' . $id . ')',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('[CharacterDataController] implants 异常: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '服务器错误',
            ], 500);
        }
    }

    /**
     * 获取克隆体信息
     * GET /api/dashboard/character/clones
     */
    public function clones(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->access_token) {
                return response()->json([
                    'success' => false,
                    'message' => '未授权访问',
                ], 401);
            }

            $data = CharacterDataService::getSingleData(
                (string) $user->eve_character_id,
                $user->access_token,
                'clones'
            );

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => '获取克隆体信息失败',
                ]);
            }

            // 收集所有需要解析的 ID
            $locationIds = [];
            $implantIds = [];

            // 基地位置
            if (isset($data['home_location']['location_id'])) {
                $locationIds[] = $data['home_location']['location_id'];
            }

            // 跳跃克隆
            $jumpClones = $data['jump_clones'] ?? [];
            foreach ($jumpClones as $clone) {
                if (isset($clone['location_id'])) {
                    $locationIds[] = $clone['location_id'];
                }
                if (isset($clone['implants']) && is_array($clone['implants'])) {
                    foreach ($clone['implants'] as $implantId) {
                        $implantIds[] = $implantId;
                    }
                }
            }

            // 批量解析名称 - 传入克隆体数据以获取 location_type
            $allCloneData = $jumpClones;
            if (isset($data['home_location'])) {
                $allCloneData[] = $data['home_location'];
            }
            $locationNames = $this->resolveLocationNames(array_unique($locationIds), $allCloneData);
            $implantNames = !empty($implantIds) 
                ? $this->eveDataService->getNamesByIds(array_unique($implantIds)) 
                : [];

            // 构建结果
            $result = [
                'last_clone_jump_date' => $data['last_clone_jump_date'] ?? null,
                'last_station_change_date' => $data['last_station_change_date'] ?? null,
            ];

            // 处理基地位置
            if (isset($data['home_location'])) {
                $homeLocationId = $data['home_location']['location_id'] ?? null;
                $result['home_location'] = [
                    'location_id' => $homeLocationId,
                    'location_type' => $data['home_location']['location_type'] ?? 'unknown',
                    'location_name' => $locationNames[$homeLocationId] ?? null,
                ];
            }

            // 处理跳跃克隆
            $result['jump_clones'] = [];
            foreach ($jumpClones as $clone) {
                $locationId = $clone['location_id'] ?? null;
                $cloneData = [
                    'jump_clone_id' => $clone['jump_clone_id'] ?? null,
                    'location_id' => $locationId,
                    'location_type' => $clone['location_type'] ?? 'unknown',
                    'location_name' => $locationNames[$locationId] ?? '未知位置',
                    'implants' => [],
                ];

                // 处理克隆体的植入体
                if (isset($clone['implants']) && is_array($clone['implants'])) {
                    foreach ($clone['implants'] as $implantId) {
                        $cloneData['implants'][] = [
                            'id' => $implantId,
                            'name' => $implantNames[$implantId] ?? '未知植入体',
                        ];
                    }
                }

                $result['jump_clones'][] = $cloneData;
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('[CharacterDataController] clones 异常: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '服务器错误',
            ], 500);
        }
    }

    /**
     * 获取雇佣历史
     * GET /api/dashboard/character/corphistory
     */
    public function corpHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->access_token) {
                return response()->json([
                    'success' => false,
                    'message' => '未授权访问',
                ], 401);
            }

            $data = CharacterDataService::getSingleData(
                (string) $user->eve_character_id,
                $user->access_token,
                'history'
            );

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => '获取雇佣历史失败',
                ]);
            }

            // 收集所有军团 ID
            $corpIds = [];
            foreach ($data as $record) {
                if (isset($record['corporation_id'])) {
                    $corpIds[] = $record['corporation_id'];
                }
            }

            // 批量解析军团名称
            $corpNames = $this->resolveEntityNames(array_unique($corpIds));

            // 按 start_date 降序排列
            usort($data, function ($a, $b) {
                return strtotime($b['start_date'] ?? 0) - strtotime($a['start_date'] ?? 0);
            });

            // 构建结果，计算每段雇佣的天数
            $result = [];
            $totalRecords = count($data);
            
            foreach ($data as $index => $record) {
                $corpId = $record['corporation_id'] ?? null;
                $startDate = $record['start_date'] ?? null;
                
                // 计算结束日期：下一条记录的开始日期，或者"至今"
                $endDate = ($index === 0) ? '至今' : null;
                
                // 如果不是最近的记录，取下一条的 start_date 作为这条的 end_date
                if ($index > 0 && isset($data[$index - 1]['start_date'])) {
                    $endDate = Carbon::parse($data[$index - 1]['start_date'])->format('Y-m-d');
                }

                // 计算天数
                $days = 0;
                if ($startDate) {
                    $startCarbon = Carbon::parse($startDate);
                    if ($index === 0) {
                        // 当前军团，计算到现在的天数
                        $days = $startCarbon->diffInDays(Carbon::now());
                    } elseif (isset($data[$index - 1]['start_date'])) {
                        // 之前的军团，计算到下一条记录开始日期
                        $endCarbon = Carbon::parse($data[$index - 1]['start_date']);
                        $days = $startCarbon->diffInDays($endCarbon);
                    }
                }

                $result[] = [
                    'record_id' => $record['record_id'] ?? null,
                    'corporation_id' => $corpId,
                    'corporation_name' => $corpNames[$corpId] ?? '未知军团',
                    'start_date' => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
                    'end_date' => $endDate,
                    'days' => (int) $days,
                    'is_deleted' => $record['is_deleted'] ?? false,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('[CharacterDataController] corpHistory 异常: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '服务器错误',
            ], 500);
        }
    }

    /**
     * 解析位置名称（空间站/建筑）
     * 直接调用 ESI 的 stations/structures 接口获取中文名
     * 
     * @param array $locationIds 位置 ID 数组
     * @param array $cloneData 克隆体数据数组，用于获取 location_type
     * @return array [id => name]
     */
    private function resolveLocationNames(array $locationIds, array $cloneData = []): array
    {
        if (empty($locationIds)) {
            return [];
        }

        $names = [];
        $baseUrl = rtrim(config('esi.base_url'), '/');
        
        // 构建 location_type 映射
        $locationTypes = [];
        foreach ($cloneData as $clone) {
            if (isset($clone['location_id']) && isset($clone['location_type'])) {
                $locationTypes[$clone['location_id']] = $clone['location_type'];
            }
        }
        
        foreach ($locationIds as $id) {
            // 检查缓存
            $cacheKey = "eve_location_name_zh_{$id}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $names[$id] = $cached;
                continue;
            }
            
            $name = null;
            
            // 判断位置类型：NPC 空间站 ID 范围是 60000000-69999999
            $isStation = ($locationTypes[$id] ?? null) === 'station' || ($id >= 60000000 && $id < 70000000);
            
            try {
                if ($isStation) {
                    // NPC 空间站 - 国服 Serenity 的 stations 接口直接返回中文名
                    $response = Http::timeout(10)
                        ->get($baseUrl . "/universe/stations/{$id}/", ['datasource' => 'serenity']);
                    if ($response->ok()) {
                        $name = $response->json()['name'] ?? null;
                    }
                } else {
                    // 玩家建筑 - structures 接口需要 token，这里尝试获取用户 token
                    $user = Auth::user();
                    if ($user && $user->access_token) {
                        $response = Http::withToken($user->access_token)->timeout(10)
                            ->get($baseUrl . "/universe/structures/{$id}/", ['datasource' => 'serenity']);
                        if ($response->ok()) {
                            $name = $response->json()['name'] ?? null;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::debug("[CharacterDataController] 解析位置名称失败 {$id}: " . $e->getMessage());
            }
            
            if ($name) {
                $names[$id] = $name;
                Cache::put($cacheKey, $name, 86400);
            } else {
                $names[$id] = "未知位置 (ID: {$id})";
            }
        }

        return $names;
    }

    /**
     * 解析实体名称（军团、联盟、角色等）
     * 
     * @param array $ids ID 数组
     * @return array [id => name]
     */
    private function resolveEntityNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        try {
            $baseUrl = rtrim(config('esi.base_url'), '/');
            $datasource = config('esi.datasource', 'serenity');
            
            // 使用 universe/names 接口批量解析
            $response = Http::timeout(10)->post(
                $baseUrl . '/universe/names/?datasource=' . $datasource . '&language=zh',
                array_values($ids)
            );
            
            if ($response->ok()) {
                $names = [];
                foreach ($response->json() as $item) {
                    if (isset($item['id']) && isset($item['name'])) {
                        $names[$item['id']] = $item['name'];
                    }
                }
                return $names;
            }
        } catch (\Exception $e) {
            Log::debug('[CharacterDataController] 解析实体名称失败: ' . $e->getMessage());
        }

        return [];
    }
}
