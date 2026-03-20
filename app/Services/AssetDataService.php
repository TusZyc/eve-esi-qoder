<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Helpers\EveHelper;
use App\Services\EveDataService;

/**
 * 资产数据服务
 * 
 * 提供角色资产数据获取、位置信息解析、空间站名称翻译等功能
 */
class AssetDataService
{
    /**
     * 获取缓存的原始资产数据，未缓存则重新拉取
     */
    public function getCachedAssets($user): ?array
    {
        $cacheKey = "assets_raw_{$user->eve_character_id}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $assets = $this->fetchAllAssets($user);
        if ($assets !== null) {
            Cache::put($cacheKey, $assets, 900);
        }
        return $assets;
    }

    /**
     * 从 ESI 获取所有资产数据
     */
    public function fetchAllAssets($user): ?array
    {
        $baseUrl = config('esi.base_url') . "characters/{$user->eve_character_id}/assets/";
        $params = ['page' => 1, 'datasource' => 'serenity'];

        $response = Http::timeout(15)
            ->withToken($user->access_token)
            ->get($baseUrl, $params);

        if ($response->failed()) {
            $status = $response->status();
            Log::error('[Assets] 资产获取失败', ['status' => $status, 'body' => substr($response->body(), 0, 200)]);
            if ($status === 404) {
                return [];
            }
            return null;
        }

        $allAssets = $response->json();
        $totalPages = (int) ($response->header('X-Pages') ?? 1);

        if ($totalPages > 1) {
            $responses = Http::pool(function ($pool) use ($baseUrl, $user, $totalPages) {
                for ($page = 2; $page <= $totalPages; $page++) {
                    $pool->as("page_{$page}")
                        ->timeout(15)
                        ->withToken($user->access_token)
                        ->get($baseUrl, ['page' => $page, 'datasource' => 'serenity']);
                }
            });

            for ($page = 2; $page <= $totalPages; $page++) {
                $key = "page_{$page}";
                try {
                    $resp = $responses[$key] ?? null;
                    if ($resp instanceof \Illuminate\Http\Client\Response && $resp->ok()) {
                        $allAssets = array_merge($allAssets, $resp->json());
                    }
                } catch (\Exception $e) {
                    Log::warning("[Assets] 第 {$page} 页获取失败");
                }
            }
        }

        Log::info('[Assets] 获取资产完成', ['count' => count($allAssets), 'pages' => $totalPages]);
        return $allAssets;
    }

    /**
     * 获取位置信息（名称 + 所属星系ID）
     */
    public function getLocationInfo(array $locationIds, array $locationTypes, string $accessToken): array
    {
        $info = [];
        if (empty($locationIds)) {
            return $info;
        }

        // 加载本地数据
        $eveData = app(EveDataService::class);
        $namesDb = $eveData->getItemDatabase();
        $stationSysMap = $eveData->getStationSystemMap();

        $uncachedStations = [];
        $uncachedStructures = [];

        foreach ($locationIds as $id) {
            $cached = Cache::get("eve_locinfo_{$id}");
            if ($cached !== null) {
                $info[$id] = $cached;
                continue;
            }

            $locType = $locationTypes[$id] ?? 'other';

            if ($locType === 'solar_system') {
                $locInfo = [
                    'name' => '太空中的物品',
                    'system_id' => (int) $id,
                ];
                $info[$id] = $locInfo;
                Cache::put("eve_locinfo_{$id}", $locInfo, 86400);
            } elseif ($id > 1000000000000) {
                // 玩家建筑：先尝试本地查找
                $localName = $namesDb[(int) $id] ?? null;
                $localSysId = $stationSysMap[(int) $id] ?? null;
                if ($localName && $localSysId) {
                    $locInfo = ['name' => $localName, 'system_id' => (int) $localSysId];
                    $info[$id] = $locInfo;
                    Cache::put("eve_locinfo_{$id}", $locInfo, 86400);
                } else {
                    $uncachedStructures[] = $id;
                }
            } else {
                // NPC空间站：先尝试本地查找
                $localName = $namesDb[(int) $id] ?? null;
                $localSysId = $stationSysMap[(int) $id] ?? null;
                if ($localName && $localSysId) {
                    $locInfo = ['name' => $localName, 'system_id' => (int) $localSysId];
                    $info[$id] = $locInfo;
                    Cache::put("eve_locinfo_{$id}", $locInfo, 86400);
                } else {
                    $uncachedStations[] = $id;
                }
            }
        }

        // 查询空间站：获取 station 数据后逐段翻译站名
        if (!empty($uncachedStations)) {
            $this->fetchAndTranslateStations($uncachedStations, $info);
        }

        // 并发查询建筑
        if (!empty($uncachedStructures)) {
            $this->fetchStructures($uncachedStructures, $accessToken, $info);
        }

        return $info;
    }

    /**
     * 获取并翻译空间站名称
     */
    protected function fetchAndTranslateStations(array $stationIds, array &$info): void
    {
        $baseUrl = config('esi.base_url');

        // 1) 获取空间站详情
        $stationData = [];
        $batches = array_chunk($stationIds, 5);
        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                foreach ($batch as $stationId) {
                    $pool->as("station_{$stationId}")
                        ->timeout(5)
                        ->get($baseUrl . "universe/stations/{$stationId}/", ['datasource' => 'serenity']);
                }
            });
            foreach ($batch as $stationId) {
                $key = "station_{$stationId}";
                try {
                    $response = $responses[$key] ?? null;
                    if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                        $data = $response->json();
                        $stationData[$stationId] = [
                            'name' => $data['name'] ?? "空间站 {$stationId}",
                            'system_id' => $data['system_id'] ?? 0,
                            'owner' => $data['owner'] ?? 0,
                        ];
                    } else {
                        $status = $response instanceof \Illuminate\Http\Client\Response ? $response->status() : 0;
                        Log::warning('[Assets] NPC空间站查询失败', [
                            'station_id' => $stationId,
                            'status' => $status,
                        ]);
                        $stationData[$stationId] = ['name' => "未知位置 ({$stationId})", 'system_id' => 0, 'owner' => 0];
                    }
                } catch (\Exception $e) {
                    Log::warning('[Assets] NPC空间站查询异常', [
                        'station_id' => $stationId,
                        'error' => $e->getMessage(),
                    ]);
                    $stationData[$stationId] = ['name' => "未知位置 ({$stationId})", 'system_id' => 0, 'owner' => 0];
                }
            }
        }

        // 2) 获取中英文星系名
        $sysIds = array_values(array_unique(array_filter(array_column($stationData, 'system_id'))));
        $zhSysNames = [];
        $enSysNames = [];
        if (!empty($sysIds)) {
            $this->fetchSystemNames($sysIds, $zhSysNames, $enSysNames);
        }

        // 3) 获取中英文军团名
        $ownerIds = array_values(array_unique(array_filter(array_column($stationData, 'owner'))));
        $zhCorpNames = [];
        $enCorpNames = [];
        if (!empty($ownerIds)) {
            $this->fetchCorpNames($ownerIds, $zhCorpNames, $enCorpNames);
        }

        // 4) 设施类型中英文映射
        $facilityMap = $this->getFacilityMap();

        // 5) 逐段翻译站名
        foreach ($stationData as $stationId => $data) {
            $name = $data['name'];
            $sysId = $data['system_id'];
            $ownerId = $data['owner'];

            // 替换星系名
            $enSys = $enSysNames[$sysId] ?? '';
            $zhSys = $zhSysNames[$sysId] ?? '';
            if ($enSys && $zhSys && str_starts_with($name, $enSys)) {
                $name = $zhSys . substr($name, strlen($enSys));
            }

            // Moon -> 卫星
            $name = preg_replace('/\bMoon\b/', '卫星', $name);

            // 替换军团名
            $enCorp = $enCorpNames[$ownerId] ?? '';
            $zhCorp = $zhCorpNames[$ownerId] ?? '';
            if ($enCorp && $zhCorp) {
                $name = str_replace($enCorp, $zhCorp, $name);
            }

            // 替换设施类型
            foreach ($facilityMap as $en => $zh) {
                if (str_contains($name, $en)) {
                    $name = str_replace($en, $zh, $name);
                    break;
                }
            }

            $locInfo = ['name' => $name, 'system_id' => $sysId];
            $info[$stationId] = $locInfo;
            // 失败结果（包含"未知"）仅缓存5分钟，成功结果缓存1天
            $cacheTtl = str_contains($name, '未知') ? 300 : 86400;
            Cache::put("eve_locinfo_{$stationId}", $locInfo, $cacheTtl);
        }
    }

    /**
     * 获取中英文星系名
     */
    protected function fetchSystemNames(array $sysIds, array &$zhNames, array &$enNames): void
    {
        $baseUrl = config('esi.base_url');
        $uncachedIds = [];
        
        // 先从本地数据获取中文星系名
        foreach ($sysIds as $sysId) {
            $localInfo = EveDataService::getLocalSystemInfo($sysId);
            if ($localInfo && isset($localInfo['name'])) {
                $zhNames[$sysId] = $localInfo['name'];
                Cache::put("eve_sysname_{$sysId}", $localInfo['name'], 86400);
            } else {
                $uncachedIds[] = $sysId;
            }
        }
        
        // 本地没有的，调用 ESI API 兜底
        if (empty($uncachedIds)) {
            // 英文名仍需要从 API 获取（用于站名翻译）
            $uncachedIds = $sysIds;
        }
        
        $sysBatches = array_chunk($uncachedIds, 5);
        foreach ($sysBatches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                foreach ($batch as $sysId) {
                    // 只有中文名未获取时才请求中文
                    $pool->as("zh_{$sysId}")->timeout(5)
                        ->get($baseUrl . "universe/systems/{$sysId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                    // 英文名用于站名翻译
                    $pool->as("en_{$sysId}")->timeout(5)
                        ->get($baseUrl . "universe/systems/{$sysId}/", ['datasource' => 'serenity', 'language' => 'en']);
                }
            });
            foreach ($batch as $sysId) {
                try {
                    // 只有中文名未获取时才使用 API 结果
                    if (!isset($zhNames[$sysId])) {
                        $r = $responses["zh_{$sysId}"] ?? null;
                        if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                            $zhNames[$sysId] = $r->json()['name'] ?? '';
                            Cache::put("eve_sysname_{$sysId}", $zhNames[$sysId], 86400);
                        }
                    }
                    $r = $responses["en_{$sysId}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $enNames[$sysId] = $r->json()['name'] ?? '';
                    }
                } catch (\Exception $e) {}
            }
        }
    }

    /**
     * 获取中英文军团名
     */
    protected function fetchCorpNames(array $ownerIds, array &$zhNames, array &$enNames): void
    {
        $baseUrl = config('esi.base_url');

        // 中文名 via universe/names
        try {
            $resp = Http::timeout(10)->post($baseUrl . 'universe/names/', $ownerIds);
            if ($resp->ok()) {
                foreach ($resp->json() as $item) {
                    $zhNames[$item['id']] = $item['name'];
                }
            }
        } catch (\Exception $e) {}

        // 英文名 via corporations/{id}
        $corpBatches = array_chunk($ownerIds, 5);
        foreach ($corpBatches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                foreach ($batch as $corpId) {
                    $pool->as("corp_{$corpId}")->timeout(5)
                        ->get($baseUrl . "corporations/{$corpId}/", ['datasource' => 'serenity']);
                }
            });
            foreach ($batch as $corpId) {
                try {
                    $r = $responses["corp_{$corpId}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $enNames[$corpId] = $r->json()['name'] ?? '';
                    }
                } catch (\Exception $e) {}
            }
        }
    }

    /**
     * 获取建筑信息
     */
    protected function fetchStructures(array $structureIds, string $accessToken, array &$info): void
    {
        $baseUrl = config('esi.base_url');
        $batches = array_chunk($structureIds, 5);

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $accessToken) {
                foreach ($batch as $structureId) {
                    $pool->as("struct_{$structureId}")
                        ->timeout(5)
                        ->withToken($accessToken)
                        ->get($baseUrl . "universe/structures/{$structureId}/", ['datasource' => 'serenity']);
                }
            });

            foreach ($batch as $structureId) {
                $key = "struct_{$structureId}";
                try {
                    $response = $responses[$key] ?? null;
                    if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                        $data = $response->json();
                        $locInfo = [
                            'name' => $data['name'] ?? "建筑 {$structureId}",
                            'system_id' => $data['solar_system_id'] ?? 0,
                        ];
                    } else {
                        $status = $response instanceof \Illuminate\Http\Client\Response ? $response->status() : 0;
                        $errorBody = $response instanceof \Illuminate\Http\Client\Response ? substr($response->body(), 0, 200) : '';
                        
                        // 区分不同错误类型，并设置不同的显示名称
                        [$reason, $displayName] = match ($status) {
                            401 => ['token过期', "授权过期建筑 ({$structureId})"],
                            403 => ['无权限访问该建筑', "权限受限建筑 ({$structureId})"],
                            404 => ['建筑不存在（可能已被拆除）', "已拆除建筑 ({$structureId})"],
                            default => ['网络/服务器错误', "未知建筑 ({$structureId})"],
                        };
                        
                        Log::warning('[Assets] 玩家建筑查询失败', [
                            'structure_id' => $structureId,
                            'status' => $status,
                            'reason' => $reason,
                            'body' => $errorBody,
                        ]);
                        $locInfo = ['name' => $displayName, 'system_id' => 0];
                    }
                } catch (\Exception $e) {
                    Log::warning('[Assets] 玩家建筑查询异常', [
                        'structure_id' => $structureId,
                        'error' => $e->getMessage(),
                    ]);
                    $locInfo = ['name' => "未知建筑 ({$structureId})", 'system_id' => 0];
                }
                $info[$structureId] = $locInfo;
                // 失败结果（包含特殊标记）仅缓存5分钟，成功结果缓存1天
                $isFailedResult = str_contains($locInfo['name'], '未知') 
                    || str_contains($locInfo['name'], '权限受限') 
                    || str_contains($locInfo['name'], '已拆除') 
                    || str_contains($locInfo['name'], '授权过期');
                $cacheTtl = $isFailedResult ? 300 : 86400;
                Cache::put("eve_locinfo_{$structureId}", $locInfo, $cacheTtl);
            }
        }
    }

    /**
     * 获取星系名称
     */
    public function getSolarSystemNames(array $systemIds): array
    {
        $names = [];
        if (empty($systemIds)) {
            return $names;
        }

        $eveData = app(EveDataService::class);
        $namesDb = $eveData->getItemDatabase();

        $uncached = [];
        foreach ($systemIds as $id) {
            $cached = Cache::get("eve_sysname_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } elseif (isset($namesDb[(int) $id])) {
                $name = $namesDb[(int) $id];
                $names[$id] = $name;
                Cache::put("eve_sysname_{$id}", $name, 86400);
            } else {
                $uncached[] = $id;
            }
        }

        if (!empty($uncached)) {
            $baseUrl = config('esi.base_url');
            $batches = array_chunk($uncached, 5);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $systemId) {
                        $pool->as("sys_{$systemId}")
                            ->timeout(5)
                            ->get($baseUrl . "universe/systems/{$systemId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                    }
                });

                foreach ($batch as $systemId) {
                    $key = "sys_{$systemId}";
                    try {
                        $response = $responses[$key] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $name = $data['name'] ?? "未知星系 ({$systemId})";
                        } else {
                            $name = "未知星系 ({$systemId})";
                        }
                    } catch (\Exception $e) {
                        $name = "未知星系 ({$systemId})";
                    }
                    $names[$systemId] = $name;
                    Cache::put("eve_sysname_{$systemId}", $name, 86400);
                }
            }
        }

        return $names;
    }

    /**
     * 获取类型名称（优先本地数据库，fallback 用 ESI 中文）
     */
    public function getTypeNamesChinese(array $typeIds): array
    {
        $names = EveHelper::getNamesByIds($typeIds, 'item');

        $missing = [];
        foreach ($typeIds as $typeId) {
            if (!isset($names[$typeId]) || $names[$typeId] === '' || str_starts_with($names[$typeId], '未知')) {
                $missing[] = $typeId;
            }
        }

        if (empty($missing)) {
            return $names;
        }

        $baseUrl = config('esi.base_url');
        $batches = array_chunk($missing, 500);

        foreach ($batches as $batch) {
            try {
                $response = Http::timeout(10)
                    ->post($baseUrl . 'universe/names/', array_values($batch));
                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        $names[$item['id']] = $item['name'];
                    }
                }
            } catch (\Exception $e) {
                foreach ($batch as $typeId) {
                    if (isset($names[$typeId]) && !str_starts_with($names[$typeId], '未知')) {
                        continue;
                    }
                    $cached = Cache::get("eve_typename_{$typeId}");
                    if ($cached !== null) {
                        $names[$typeId] = $cached;
                        continue;
                    }
                    try {
                        $resp = Http::timeout(5)->get($baseUrl . "universe/types/{$typeId}/", [
                            'datasource' => 'serenity',
                            'language' => 'zh',
                        ]);
                        if ($resp->ok()) {
                            $data = $resp->json();
                            $name = $data['name'] ?? '';
                            if ($name) {
                                $names[$typeId] = $name;
                                Cache::put("eve_typename_{$typeId}", $name, 86400);
                            }
                        }
                    } catch (\Exception $ex) {}
                }
            }
        }

        return $names;
    }

    /**
     * 获取类型详情（体积、分组）
     */
    public function getTypeDetails(array $typeIds): array
    {
        $details = [];
        $uncached = [];

        foreach ($typeIds as $typeId) {
            $cached = Cache::get("eve_type_{$typeId}");
            if ($cached !== null) {
                $details[$typeId] = $cached;
            } else {
                $uncached[] = $typeId;
            }
        }

        if (!empty($uncached)) {
            $baseUrl = config('esi.base_url');
            $batches = array_chunk($uncached, 10);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $typeId) {
                        $pool->as("type_{$typeId}")
                            ->timeout(5)
                            ->get($baseUrl . "universe/types/{$typeId}/", ['datasource' => 'serenity']);
                    }
                });

                foreach ($batch as $typeId) {
                    $key = "type_{$typeId}";
                    try {
                        $response = $responses[$key] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $detail = [
                                'volume' => $data['volume'] ?? 0,
                                'group_id' => $data['group_id'] ?? 0,
                            ];
                        } else {
                            $detail = ['volume' => 0, 'group_id' => 0];
                        }
                    } catch (\Exception $e) {
                        $detail = ['volume' => 0, 'group_id' => 0];
                    }
                    Cache::put("eve_type_{$typeId}", $detail, 86400);
                    $details[$typeId] = $detail;
                }
            }
        }

        return $details;
    }

    /**
     * 获取分组信息
     */
    public function getGroupNames(array $groupIds): array
    {
        $groups = [];
        $uncached = [];

        foreach ($groupIds as $groupId) {
            $cached = Cache::get("eve_groupinfo_zh_{$groupId}");
            if ($cached !== null) {
                $groups[$groupId] = $cached;
            } else {
                $uncached[] = $groupId;
            }
        }

        if (!empty($uncached)) {
            $baseUrl = config('esi.base_url');
            $batches = array_chunk($uncached, 10);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $groupId) {
                        $pool->as("group_{$groupId}")
                            ->timeout(5)
                            ->get($baseUrl . "universe/groups/{$groupId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                    }
                });

                foreach ($batch as $groupId) {
                    $key = "group_{$groupId}";
                    try {
                        $response = $responses[$key] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $groupInfo = [
                                'name' => $data['name'] ?? '',
                                'category_id' => $data['category_id'] ?? 0,
                            ];
                        } else {
                            $groupInfo = ['name' => '', 'category_id' => 0];
                        }
                    } catch (\Exception $e) {
                        $groupInfo = ['name' => '', 'category_id' => 0];
                    }
                    Cache::put("eve_groupinfo_zh_{$groupId}", $groupInfo, 86400);
                    $groups[$groupId] = $groupInfo;
                }
            }
        }

        return $groups;
    }

    /**
     * 获取设施类型中英文映射
     */
    protected function getFacilityMap(): array
    {
        return [
            'Assembly Plant' => '组装工厂', 'Refinery' => '精炼厂',
            'Warehouse' => '仓库', 'Storage' => '储藏设施',
            'Factory' => '工厂', 'Trading Post' => '贸易站',
            'Hub' => '集散中心', 'Academy' => '学院',
            'Logistic Support' => '后勤支援', 'Testing Facilities' => '测试设施',
            'Cloning Facility' => '克隆设施', 'Foundry' => '铸造厂',
            'Biotech Research Center' => '生物科技研究中心',
            'Research Center' => '研究中心', 'School' => '学校',
            'Treasury' => '金库', 'Bureau' => '事务局',
            'Tribunal' => '法庭', 'Mining Station' => '采矿站',
            'Accounting' => '会计处', 'Mint' => '铸币厂',
            'Shipyard' => '船坞', 'Military School' => '军事学校',
            'Station' => '空间站', 'Headquarters' => '总部',
            'Law School' => '法学院', 'Plantation' => '种植园',
            'Surveillance' => '监控站', 'Commerce' => '商务站',
            'Food Packaging' => '食品包装厂',
        ];
    }

    /**
     * 构建资产的父子关系映射
     */
    public function buildAssetMaps(array $assets): array
    {
        $itemIdSet = [];
        foreach ($assets as $asset) {
            $itemIdSet[$asset['item_id']] = true;
        }

        $childOf = [];
        $topItems = [];
        foreach ($assets as $asset) {
            $locType = $asset['location_type'] ?? 'other';
            $isChild = (in_array($locType, ['item', 'other']) && isset($itemIdSet[$asset['location_id']]));
            if ($isChild) {
                $childOf[$asset['location_id']][] = $asset['item_id'];
            } else {
                $topItems[$asset['item_id']] = [
                    'location_id' => $asset['location_id'],
                    'location_type' => $locType,
                ];
            }
        }

        return [$itemIdSet, $childOf, $topItems];
    }

    /**
     * 统计每个位置的物品数
     */
    public function countLocationItems(array $topItems): array
    {
        $locationCounts = [];
        $locationTypes = [];

        foreach ($topItems as $itemId => $locInfo) {
            $locId = $locInfo['location_id'];
            if (!isset($locationCounts[$locId])) {
                $locationCounts[$locId] = 0;
                $locationTypes[$locId] = $locInfo['location_type'];
            }
            $locationCounts[$locId] += 1;
        }

        return [$locationCounts, $locationTypes];
    }
}
