<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Helpers\EveHelper;
use App\Services\EveDataService;
use App\Models\User;

class BookmarkDataController extends Controller
{
    /**
     * ESI v1 API 基础 URL（书签接口必须使用 v1 版本）
     */
    private const ESI_V1_BASE_URL = 'https://ali-esi.evepc.163.com/v1/';

    /**
     * 获取书签数据
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->eve_character_id) {
            Log::warning("Bookmarks: 用户未登录或无角色ID");
            return response()->json(['error' => '请先登录EVE账号'], 401);
        }
        
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        
        if (!$token) {
            Log::warning("Bookmarks: 用户无 access_token", ['character_id' => $characterId]);
            return response()->json(['error' => '授权已过期，请重新登录'], 401);
        }

        Log::info("Bookmarks: 开始获取书签", [
            'character_id' => $characterId,
            'token_length' => strlen($token)
        ]);

        try {
            // 获取书签文件夹（使用新缓存键绕过旧缓存）
            $folders = $this->getFolders($characterId, $token);
            
            // 获取书签列表（使用 v3 缓存键确保获取最新数据）
            $bookmarks = Cache::remember("bookmarks_v3_{$characterId}", 300, function () use ($characterId) {
                $token = User::where('eve_character_id', $characterId)->value('access_token');
                if (!$token) return [];

                return $this->fetchAllBookmarks($characterId, $token);
            });

            if (empty($bookmarks)) {
                return response()->json([
                    'folders' => $folders,
                    'bookmarks' => []
                ]);
            }

            // 解析 location_id 到实际星系ID（简化版，出错时不阻止显示）
            $locationToSystem = [];
            try {
                $locationToSystem = $this->resolveLocationsToSystems($bookmarks, $token, $characterId);
            } catch (\Exception $e) {
                Log::warning("Bookmarks: 位置解析失败", ['error' => $e->getMessage()]);
                // 继续执行，让数据能显示出来
            }
            
            // 收集所有星系ID获取名称
            $systemIds = array_filter(array_unique(array_values($locationToSystem)));
            $systemNames = [];
            if (!empty($systemIds)) {
                try {
                    $systemNames = $this->getSystemNames($systemIds);
                    Log::info("Bookmarks: 获取到星系名称", ['count' => count($systemNames)]);
                } catch (\Exception $e) {
                    Log::warning("Bookmarks: 星系名称获取失败", ['error' => $e->getMessage()]);
                }
            }

            // 按文件夹分组
            $folderMap = [];
            foreach ($folders as $folder) {
                $folderMap[$folder['folder_id']] = $folder['name'];
            }

            // 构建返回数据
            $result = [];
            foreach ($bookmarks as $bookmark) {
                $folderId = $bookmark['folder_id'] ?? 0;
                $locationId = $bookmark['location_id'] ?? 0;
                $systemId = $locationToSystem[$locationId] ?? 0;
                
                // 确定显示的位置名称（优先显示星系名，否则显示 location_id）
                $displayName = '';
                if ($systemId && isset($systemNames[$systemId])) {
                    $displayName = $systemNames[$systemId];
                } elseif ($systemId) {
                    $displayName = "星系 #{$systemId}";
                } else {
                    $displayName = "位置 #{$locationId}";
                }
                
                $result[] = [
                    'bookmark_id' => $bookmark['bookmark_id'] ?? 0,
                    'folder_id' => $folderId,
                    'folder_name' => $folderMap[$folderId] ?? '个人地点',
                    'label' => $bookmark['label'] ?? '',
                    'notes' => $bookmark['notes'] ?? '',
                    'created' => $bookmark['created'] ?? '',
                    'creator_id' => $bookmark['creator_id'] ?? 0,
                    'location_id' => $locationId,
                    'solar_system_id' => $systemId,
                    'solar_system_name' => $displayName,
                    'coordinates' => $bookmark['coordinates'] ?? null,
                    'item' => $bookmark['item'] ?? null,
                ];
            }

            // 按文件夹分组排序
            usort($result, function($a, $b) {
                if ($a['folder_id'] !== $b['folder_id']) {
                    return $a['folder_id'] - $b['folder_id'];
                }
                return strcmp($a['label'], $b['label']);
            });

            return response()->json([
                'folders' => $folders,
                'bookmarks' => $result
            ]);

        } catch (\Exception $e) {
            Log::error("Bookmarks fetch error: " . $e->getMessage());
            
            // 处理 scope 权限不足错误
            if ($e->getMessage() === 'SCOPE_MISSING') {
                return response()->json([
                    'error' => 'scope_missing',
                    'message' => '当前角色未授权书签权限，请重新授权并勾选书签相关权限'
                ], 403);
            }
            
            return response()->json(['error' => '获取书签数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取书签文件夹
     */
    public function folders(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->eve_character_id) {
            return response()->json(['error' => '请先登录EVE账号'], 401);
        }
        
        $characterId = $user->eve_character_id;
        $token = $user->access_token;

        try {
            $folders = $this->getFolders($characterId, $token);
            return response()->json($folders);
        } catch (\Exception $e) {
            Log::error("Bookmarks: 获取文件夹失败", ['error' => $e->getMessage()]);
            return response()->json(['error' => '获取文件夹失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 从 ESI 获取所有书签（分页获取）
     * 使用 v1 版本 API: /v1/characters/{character_id}/bookmarks/
     */
    private function fetchAllBookmarks(int $characterId, string $token): array
    {
        $allBookmarks = [];
        $page = 1;
        $maxPages = 50; // 安全限制，避免无限循环
        
        do {
            Log::info("Bookmarks: 请求 ESI 第 {$page} 页", ['character_id' => $characterId]);
            
            $url = self::ESI_V1_BASE_URL . "characters/{$characterId}/bookmarks/";
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
                'Accept-Language' => 'zh',
            ])
            ->timeout(20)
            ->get($url, [
                'datasource' => 'serenity',
                'language' => 'zh',
                'page' => $page
            ]);
            
            Log::info("Bookmarks: ESI 响应", [
                'character_id' => $characterId,
                'page' => $page,
                'status' => $response->status(),
                'headers' => [
                    'X-Pages' => $response->header('X-Pages'),
                    'X-Esi-Error-Limit-Remain' => $response->header('X-Esi-Error-Limit-Remain'),
                ],
            ]);
            
            if (!$response->ok()) {
                $errorBody = $response->body();
                Log::error("Bookmarks: ESI 请求失败", [
                    'character_id' => $characterId,
                    'page' => $page,
                    'status' => $response->status(),
                    'body' => $errorBody
                ]);
                
                // 如果第一页就失败，抛出异常
                if ($page === 1) {
                    // 检测 403 权限不足错误
                    if ($response->status() === 403 && str_contains($errorBody, 'not valid for scope')) {
                        throw new \Exception('SCOPE_MISSING');
                    }
                    throw new \Exception("ESI 请求失败: HTTP {$response->status()} - {$errorBody}");
                }
                break;
            }
            
            $data = $response->json();
            
            if ($data === null) {
                Log::warning("Bookmarks: ESI 返回空数据或解析失败", [
                    'character_id' => $characterId,
                    'page' => $page,
                    'body' => $response->body()
                ]);
                break;
            }
            
            if (!is_array($data)) {
                Log::warning("Bookmarks: ESI 返回非数组数据", [
                    'character_id' => $characterId,
                    'page' => $page,
                    'type' => gettype($data)
                ]);
                break;
            }
            
            Log::info("Bookmarks: 第 {$page} 页获取到 " . count($data) . " 条书签");
            
            if (empty($data)) {
                break;
            }
            
            $allBookmarks = array_merge($allBookmarks, $data);
            
            // 获取总页数
            $totalPages = (int) $response->header('X-Pages', 1);
            $page++;
            
        } while ($page <= $totalPages && $page <= $maxPages);
        
        Log::info("Bookmarks: 总共获取到 " . count($allBookmarks) . " 条书签", [
            'character_id' => $characterId
        ]);
        
        return $allBookmarks;
    }

    /**
     * 获取文件夹数据
     * 使用 v1 版本 API: /v1/characters/{character_id}/bookmarks/folders/
     */
    private function getFolders(int $characterId, string $token): array
    {
        return Cache::remember("bookmark_folders_v3_{$characterId}", 300, function () use ($characterId) {
            $token = User::where('eve_character_id', $characterId)->value('access_token');
            if (!$token) return [];

            $url = self::ESI_V1_BASE_URL . "characters/{$characterId}/bookmarks/folders/";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
                'Accept-Language' => 'zh',
            ])
            ->timeout(15)
            ->get($url, [
                'datasource' => 'serenity',
                'language' => 'zh'
            ]);

            Log::info("Bookmarks: 文件夹 ESI 响应", [
                'character_id' => $characterId,
                'status' => $response->status(),
                'count' => $response->ok() ? count($response->json() ?? []) : 0
            ]);

            if (!$response->ok()) {
                Log::warning("Bookmarks: 文件夹请求失败", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('ESI request failed for bookmark folders');
            }

            $folders = $response->json() ?? [];
            Log::info("Bookmarks: 获取到 " . count($folders) . " 个文件夹");
            return $folders;
        });
    }

    /**
     * 批量获取星系名称
     */
    private function getSystemNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $names = [];
        foreach ($ids as $id) {
            $name = EveHelper::getNameById($id, 'system');
            if ($name) {
                $names[$id] = $name;
            }
        }
        return $names;
    }

    /**
     * 解析 location_id 到星系 ID
     * ESI书签的location_id可能是：
     * - 星系 ID: 30000000-32000000
     * - 空间站 ID: 60000000-69999999
     * - 建筑物 ID: > 1000000000000 (1万亿+)
     */
    private function resolveLocationsToSystems(array $bookmarks, string $token, int $characterId = 0): array
    {
        $locationToSystem = [];
        $stationIds = [];
        $structureIds = [];

        foreach ($bookmarks as $bookmark) {
            $locationId = $bookmark['location_id'] ?? 0;
            if (!$locationId) continue;

            // 根据ID范围判断类型
            if ($locationId >= 30000000 && $locationId < 33000000) {
                // 星系 ID，直接使用
                $locationToSystem[$locationId] = $locationId;
            } elseif ($locationId >= 60000000 && $locationId < 70000000) {
                // 空间站 ID
                $stationIds[] = $locationId;
            } elseif ($locationId > 1000000000000) {
                // 建筑物/结构 ID
                $structureIds[] = $locationId;
            } else {
                // 未知类型，标记为0
                $locationToSystem[$locationId] = 0;
            }
        }

        // 批量解析空间站ID到星系ID
        foreach (array_unique($stationIds) as $stationId) {
            $systemId = $this->getStationSystem($stationId);
            $locationToSystem[$stationId] = $systemId;
        }

        // 批量解析建筑物ID到星系ID（需要token）
        foreach (array_unique($structureIds) as $structureId) {
            $systemId = $this->getStructureSystem($structureId, $token, $characterId);
            $locationToSystem[$structureId] = $systemId;
        }

        return $locationToSystem;
    }

    /**
     * 获取空间站所在星系
     */
    private function getStationSystem(int $stationId): int
    {
        return Cache::remember("station_system_{$stationId}", 86400, function () use ($stationId) {
            // 先查本地数据
            $localInfo = EveDataService::getLocalStationInfo($stationId);
            if ($localInfo && isset($localInfo['system_id'])) {
                return (int) $localInfo['system_id'];
            }
            
            // 本地没有，调用 ESI API 兜底
            try {
                $url = config('esi.base_url') . "universe/stations/{$stationId}/";
                $response = Http::timeout(10)
                    ->get($url, [
                        'datasource' => 'serenity',
                        'language' => 'zh'
                    ]);
                
                if ($response->ok()) {
                    return $response->json('system_id') ?? 0;
                }
                Log::warning("Bookmarks: 获取空间站星系失败", [
                    'station_id' => $stationId,
                    'status' => $response->status()
                ]);
            } catch (\Exception $e) {
                Log::warning("Bookmarks: 获取空间站星系异常", [
                    'station_id' => $stationId,
                    'error' => $e->getMessage()
                ]);
            }
            return 0;
        });
    }

    /**
     * 获取建筑物所在星系（需要token）
     */
    private function getStructureSystem(int $structureId, string $token, int $characterId = 0): int
    {
        return Cache::remember("structure_system_{$structureId}", 86400, function () use ($structureId, $characterId) {
            // 先查本地数据
            $localInfo = EveDataService::getLocalStructureInfo($structureId);
            if ($localInfo && isset($localInfo['system_id'])) {
                return (int) $localInfo['system_id'];
            }

            // 本地没有，调用 ESI API 兜底
            try {
                $token = $characterId > 0
                    ? User::where('eve_character_id', $characterId)->value('access_token')
                    : null;
                if (!$token) return 0;

                $url = config('esi.base_url') . "universe/structures/{$structureId}/";
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->get($url, [
                        'datasource' => 'serenity',
                        'language' => 'zh'
                    ]);

                if ($response->ok()) {
                    return $response->json('solar_system_id') ?? 0;
                }
                Log::warning("Bookmarks: 获取建筑物星系失败", [
                    'structure_id' => $structureId,
                    'status' => $response->status()
                ]);
            } catch (\Exception $e) {
                Log::warning("Bookmarks: 获取建筑物星系异常", [
                    'structure_id' => $structureId,
                    'error' => $e->getMessage()
                ]);
            }
            return 0;
        });
    }
}
