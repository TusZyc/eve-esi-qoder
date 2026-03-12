<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Services\EveDataService;

class AssetDataController extends Controller
{
    /**
     * 第一步：获取按星系分组的位置列表
     */
    public function locations(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->eve_character_id) {
            return response()->json(['success' => false, 'error' => 'unauthorized', 'message' => '未授权'], 401);
        }
        if (empty($user->access_token)) {
            return response()->json(['success' => false, 'error' => 'no_token', 'message' => '缺少访问令牌'], 401);
        }

        try {
            $assets = $this->getCachedAssets($user);
            if ($assets === null) {
                return response()->json(['success' => false, 'error' => 'eve_api_error', 'message' => 'EVE API 请求失败'], 502);
            }
            if (empty($assets)) {
                return response()->json(['success' => true, 'data' => ['solar_systems' => []]]);
            }

            $itemIdSet = [];
            foreach ($assets as $asset) {
                $itemIdSet[$asset['item_id']] = true;
            }

            // 构建 parent -> children 和 top-level 映射
            $childOf = [];
            $topItems = []; // itemId => ['location_id' => ..., 'location_type' => ...]
            foreach ($assets as $asset) {
                $locType = $asset['location_type'] ?? 'other';
                // 修复：location_type 为 'item' 或 'other' 且 location_id 是另一个物品时，视为子项
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

            // 统计每个位置的物品数（含嵌套）
            $countChildren = function ($itemId) use (&$countChildren, &$childOf) {
                $count = 0;
                if (isset($childOf[$itemId])) {
                    foreach ($childOf[$itemId] as $childId) {
                        $count += 1 + $countChildren($childId);
                    }
                }
                return $count;
            };

            $locationCounts = [];
            $locationTypes = []; // locId => location_type
            foreach ($topItems as $itemId => $locInfo) {
                $locId = $locInfo['location_id'];
                if (!isset($locationCounts[$locId])) {
                    $locationCounts[$locId] = 0;
                    $locationTypes[$locId] = $locInfo['location_type'];
                }
                $locationCounts[$locId] += 1 + $countChildren($itemId);
            }

            // 过滤掉 0 物品的位置
            $locationCounts = array_filter($locationCounts, fn($count) => $count > 0);

            if (empty($locationCounts)) {
                return response()->json(['success' => true, 'data' => ['solar_systems' => []]]);
            }

            // 获取位置信息（名称 + 所属星系）
            $locationIds = array_keys($locationCounts);
            $locationInfo = $this->getLocationInfo($locationIds, $locationTypes, $user->access_token);

            // 按星系分组
            $systemGroups = [];
            foreach ($locationCounts as $locId => $count) {
                $info = $locationInfo[$locId] ?? ['name' => "未知位置 (ID: {$locId})", 'system_id' => 0];
                $systemId = $info['system_id'] ?? 0;

                if (!isset($systemGroups[$systemId])) {
                    $systemGroups[$systemId] = [
                        'system_id' => $systemId,
                        'locations' => [],
                    ];
                }
                $systemGroups[$systemId]['locations'][] = [
                    'location_id' => $locId,
                    'location_name' => $info['name'],
                    'item_count' => $count,
                ];
            }

            // 获取星系名称
            $systemIds = array_filter(array_keys($systemGroups), fn($id) => $id > 0);
            $systemNames = $this->getSolarSystemNames(array_values($systemIds));

            // 构建最终结果
            $solarSystems = [];
            foreach ($systemGroups as $systemId => $group) {
                $systemName = $systemNames[$systemId] ?? ($systemId > 0 ? "未知星系 (ID: {$systemId})" : '未知位置');
                $totalItems = array_sum(array_column($group['locations'], 'item_count'));

                // 位置按物品数降序排列
                usort($group['locations'], fn($a, $b) => $b['item_count'] - $a['item_count']);

                $solarSystems[] = [
                    'system_id' => $systemId,
                    'system_name' => $systemName,
                    'total_items' => $totalItems,
                    'locations' => $group['locations'],
                ];
            }

            // 星系按总物品数降序排列
            usort($solarSystems, fn($a, $b) => $b['total_items'] - $a['total_items']);

            return response()->json(['success' => true, 'data' => ['solar_systems' => $solarSystems]]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('[Assets] 连接失败：' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'connection_timeout', 'message' => '连接超时'], 503);
        } catch (\Exception $e) {
            Log::error('[Assets] 异常：' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'unknown_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 全局搜索：轻量级，只查类型名，不查体积/分组
     */
    public function search(Request $request)
    {
        $keyword = trim($request->get('q', ''));
        if (mb_strlen($keyword) < 2) {
            return response()->json(['success' => true, 'data' => ['results' => []]]);
        }

        $user = $request->user();
        if (!$user || !$user->eve_character_id) {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 401);
        }
        if (empty($user->access_token)) {
            return response()->json(['success' => false, 'error' => 'no_token'], 401);
        }

        try {
            $assets = $this->getCachedAssets($user);
            if ($assets === null || empty($assets)) {
                return response()->json(['success' => true, 'data' => ['results' => []]]);
            }

            // 构建 itemIdSet 和父子关系，找到每个物品的顶级 location_id
            $itemIdSet = [];
            $assetMap = [];
            foreach ($assets as $asset) {
                $itemIdSet[$asset['item_id']] = true;
                $assetMap[$asset['item_id']] = $asset;
            }

            // 找出每个物品的根 location_id（空间站/建筑）
            $rootLocCache = [];
            $findRootLoc = function ($asset) use (&$findRootLoc, &$itemIdSet, &$assetMap, &$rootLocCache) {
                $itemId = $asset['item_id'];
                if (isset($rootLocCache[$itemId])) {
                    return $rootLocCache[$itemId];
                }
                $locType = $asset['location_type'] ?? 'other';
                $isChild = (in_array($locType, ['item', 'other']) && isset($itemIdSet[$asset['location_id']]));
                if ($isChild && isset($assetMap[$asset['location_id']])) {
                    $rootLocCache[$itemId] = $findRootLoc($assetMap[$asset['location_id']]);
                } else {
                    $rootLocCache[$itemId] = $asset['location_id'];
                }
                return $rootLocCache[$itemId];
            };

            // 获取所有唯一 type_id 的名称
            $typeIds = array_values(array_unique(array_column($assets, 'type_id')));
            $typeNames = $this->getTypeNamesChinese($typeIds);

            // 按关键词过滤
            $kw = mb_strtolower($keyword);
            $locationResults = []; // locId => [items]
            foreach ($assets as $asset) {
                $typeName = $typeNames[$asset['type_id']] ?? '';
                if ($typeName && mb_strpos(mb_strtolower($typeName), $kw) !== false) {
                    $rootLoc = $findRootLoc($asset);
                    if (!isset($locationResults[$rootLoc])) {
                        $locationResults[$rootLoc] = [];
                    }
                    $locationResults[$rootLoc][] = [
                        'type_name' => $typeName,
                        'quantity' => $asset['quantity'] ?? 1,
                    ];
                }
            }

            if (empty($locationResults)) {
                return response()->json(['success' => true, 'data' => ['results' => []]]);
            }

            // 获取位置名称（已缓存的直接用）
            $locIds = array_keys($locationResults);
            $locNames = [];
            foreach ($locIds as $locId) {
                $cached = Cache::get("eve_locinfo_{$locId}");
                if ($cached) {
                    $locNames[$locId] = $cached['name'] ?? "位置 {$locId}";
                } else {
                    $locNames[$locId] = "位置 {$locId}";
                }
            }

            // 合并同类型物品并构建结果
            $results = [];
            foreach ($locationResults as $locId => $items) {
                $merged = [];
                foreach ($items as $item) {
                    $key = $item['type_name'];
                    if (!isset($merged[$key])) {
                        $merged[$key] = ['type_name' => $item['type_name'], 'quantity' => 0];
                    }
                    $merged[$key]['quantity'] += $item['quantity'];
                }
                $mergedItems = array_values($merged);
                usort($mergedItems, fn($a, $b) => $b['quantity'] - $a['quantity']);
                $results[] = [
                    'location_id' => $locId,
                    'location_name' => $locNames[$locId],
                    'items' => $mergedItems,
                ];
            }

            usort($results, fn($a, $b) => count($b['items']) - count($a['items']));

            return response()->json(['success' => true, 'data' => ['results' => $results]]);

        } catch (\Exception $e) {
            Log::error('[Assets] 搜索异常：' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'unknown_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 第二步：获取某个位置的物品详情（懒加载）
     */
    public function locationItems(Request $request, $locationId)
    {
        $user = $request->user();

        if (!$user || !$user->eve_character_id) {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 401);
        }
        if (empty($user->access_token)) {
            return response()->json(['success' => false, 'error' => 'no_token'], 401);
        }

        try {
            set_time_limit(120);
            $locationId = (int) $locationId;

            // 检查位置物品缓存
            $locCacheKey = "assets_loc_{$user->eve_character_id}_{$locationId}";
            $cached = Cache::get($locCacheKey);
            if ($cached !== null) {
                return response()->json(['success' => true, 'data' => ['items' => $cached]]);
            }

            $assets = $this->getCachedAssets($user);
            if ($assets === null || empty($assets)) {
                return response()->json(['success' => false, 'error' => 'no_data'], 502);
            }

            // 构建映射
            $assetMap = [];
            $itemIdSet = [];
            foreach ($assets as $asset) {
                $assetMap[$asset['item_id']] = $asset;
                $itemIdSet[$asset['item_id']] = true;
            }

            $childOf = [];
            $topItemsAtLoc = [];
            foreach ($assets as $asset) {
                $locType = $asset['location_type'] ?? 'other';
                // 修复：同时检查 'item' 和 'other'
                $isChild = (in_array($locType, ['item', 'other']) && isset($itemIdSet[$asset['location_id']]));
                if ($isChild) {
                    $childOf[$asset['location_id']][] = $asset['item_id'];
                } else {
                    if ($asset['location_id'] == $locationId) {
                        $topItemsAtLoc[] = $asset['item_id'];
                    }
                }
            }

            // 收集该位置所有相关物品ID
            $allItemIds = [];
            $collectAll = function ($itemId) use (&$collectAll, &$childOf, &$allItemIds) {
                $allItemIds[] = $itemId;
                if (isset($childOf[$itemId])) {
                    foreach ($childOf[$itemId] as $childId) {
                        $collectAll($childId);
                    }
                }
            };
            foreach ($topItemsAtLoc as $itemId) {
                $collectAll($itemId);
            }

            // 只获取该位置需要的 type IDs
            $typeIds = [];
            foreach ($allItemIds as $itemId) {
                if (isset($assetMap[$itemId])) {
                    $typeIds[] = $assetMap[$itemId]['type_id'];
                }
            }
            $typeIds = array_values(array_unique($typeIds));

            $typeNames = $this->getTypeNamesChinese($typeIds);
            $typeDetails = $this->getTypeDetails($typeIds);

            $groupIds = [];
            foreach ($typeDetails as $detail) {
                if (!empty($detail['group_id'])) {
                    $groupIds[] = $detail['group_id'];
                }
            }
            $groupIds = array_values(array_unique($groupIds));
            $groupNames = $this->getGroupNames($groupIds);

            // 构建树
            $buildNode = function ($itemId) use (&$buildNode, &$assetMap, &$childOf, &$typeNames, &$typeDetails, &$groupNames) {
                $asset = $assetMap[$itemId];
                $typeId = $asset['type_id'];
                $detail = $typeDetails[$typeId] ?? ['volume' => 0, 'group_id' => 0];
                $groupId = $detail['group_id'];

                $node = [
                    'item_id' => $asset['item_id'],
                    'type_id' => $typeId,
                    'type_name' => $typeNames[$typeId] ?? "未知物品 ({$typeId})",
                    'quantity' => $asset['quantity'] ?? 1,
                    'location_flag' => $asset['location_flag'] ?? 'Unknown',
                    'is_singleton' => $asset['is_singleton'] ?? false,
                    'volume' => $detail['volume'],
                    'group_name' => $groupNames[$groupId] ?? '',
                    'children' => [],
                ];

                if (isset($childOf[$itemId])) {
                    foreach ($childOf[$itemId] as $childId) {
                        $node['children'][] = $buildNode($childId);
                    }
                }

                return $node;
            };

            $items = [];
            foreach ($topItemsAtLoc as $itemId) {
                $items[] = $buildNode($itemId);
            }

            // 缓存15分钟
            Cache::put($locCacheKey, $items, 900);

            return response()->json(['success' => true, 'data' => ['items' => $items]]);

        } catch (\Exception $e) {
            Log::error("[Assets] 位置 {$locationId} 物品加载异常：" . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'unknown_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取位置信息（名称 + 所属星系ID）
     */
    private function getLocationInfo(array $locationIds, array $locationTypes, string $accessToken): array
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
            $baseUrl = config('esi.base_url');

            // 1) 获取空间站详情（name, system_id, owner）
            $stationData = [];
            $batches = array_chunk($uncachedStations, 5);
            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $stationId) {
                        $pool->as("station_{$stationId}")
                            ->timeout(5)
                            ->get($baseUrl . "universe/stations/{$stationId}/", [
                                'datasource' => 'serenity',
                            ]);
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
                            $stationData[$stationId] = ['name' => "未知位置 ({$stationId})", 'system_id' => 0, 'owner' => 0];
                        }
                    } catch (\Exception $e) {
                        $stationData[$stationId] = ['name' => "未知位置 ({$stationId})", 'system_id' => 0, 'owner' => 0];
                    }
                }
            }

            // 2) 获取中英文星系名（用于替换站名中的星系部分）
            $sysIds = array_values(array_unique(array_filter(array_column($stationData, 'system_id'))));
            $zhSysNames = [];
            $enSysNames = [];
            if (!empty($sysIds)) {
                $sysBatches = array_chunk($sysIds, 5);
                foreach ($sysBatches as $batch) {
                    $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                        foreach ($batch as $sysId) {
                            $pool->as("zh_{$sysId}")->timeout(5)
                                ->get($baseUrl . "universe/systems/{$sysId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                            $pool->as("en_{$sysId}")->timeout(5)
                                ->get($baseUrl . "universe/systems/{$sysId}/", ['datasource' => 'serenity', 'language' => 'en']);
                        }
                    });
                    foreach ($batch as $sysId) {
                        try {
                            $r = $responses["zh_{$sysId}"] ?? null;
                            if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                                $zhSysNames[$sysId] = $r->json()['name'] ?? '';
                                Cache::put("eve_sysname_{$sysId}", $zhSysNames[$sysId], 86400);
                            }
                            $r = $responses["en_{$sysId}"] ?? null;
                            if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                                $enSysNames[$sysId] = $r->json()['name'] ?? '';
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            // 3) 获取中英文军团名
            $ownerIds = array_values(array_unique(array_filter(array_column($stationData, 'owner'))));
            $zhCorpNames = [];
            $enCorpNames = [];
            if (!empty($ownerIds)) {
                // 中文名 via universe/names
                try {
                    $resp = Http::timeout(10)->post($baseUrl . 'universe/names/', $ownerIds);
                    if ($resp->ok()) {
                        foreach ($resp->json() as $item) {
                            $zhCorpNames[$item['id']] = $item['name'];
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
                                $enCorpNames[$corpId] = $r->json()['name'] ?? '';
                            }
                        } catch (\Exception $e) {}
                    }
                }
            }

            // 4) 设施类型中英文映射
            $facilityMap = [
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
                Cache::put("eve_locinfo_{$stationId}", $locInfo, 86400);
            }
        }

        // 并发查询建筑（每批5个，减少内存压力）
        if (!empty($uncachedStructures)) {
            $baseUrl = config('esi.base_url');
            $batches = array_chunk($uncachedStructures, 5);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $accessToken) {
                    foreach ($batch as $structureId) {
                        $pool->as("struct_{$structureId}")
                            ->timeout(5)
                            ->withToken($accessToken)
                            ->get($baseUrl . "universe/structures/{$structureId}/", [
                                'datasource' => 'serenity',
                            ]);
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
                            $locInfo = ['name' => "未知建筑 ({$structureId})", 'system_id' => 0];
                        }
                    } catch (\Exception $e) {
                        $locInfo = ['name' => "未知建筑 ({$structureId})", 'system_id' => 0];
                    }
                    $info[$structureId] = $locInfo;
                    Cache::put("eve_locinfo_{$structureId}", $locInfo, 86400);
                }
            }
        }

        return $info;
    }

    /**
     * 获取星系名称
     */
    private function getSolarSystemNames(array $systemIds): array
    {
        $names = [];
        if (empty($systemIds)) {
            return $names;
        }

        // 加载本地数据
        $eveData = app(EveDataService::class);
        $namesDb = $eveData->getItemDatabase();

        $uncached = [];
        foreach ($systemIds as $id) {
            $cached = Cache::get("eve_sysname_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } elseif (isset($namesDb[(int) $id])) {
                // 本地数据库有该星系名称
                $name = $namesDb[(int) $id];
                $names[$id] = $name;
                Cache::put("eve_sysname_{$id}", $name, 86400);
            } else {
                $uncached[] = $id;
            }
        }

        // 仅对本地没有的星系调用 ESI
        if (!empty($uncached)) {
            $baseUrl = config('esi.base_url');
            $batches = array_chunk($uncached, 5);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $systemId) {
                        $pool->as("sys_{$systemId}")
                            ->timeout(5)
                            ->get($baseUrl . "universe/systems/{$systemId}/", [
                                'datasource' => 'serenity',
                                'language' => 'zh',
                            ]);
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
     * 获取缓存的原始资产数据，未缓存则重新拉取
     */
    private function getCachedAssets($user): ?array
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

    private function fetchAllAssets($user): ?array
    {
        $baseUrl = config('esi.base_url') . "characters/{$user->eve_character_id}/assets/";
        $params = ['page' => 1, 'datasource' => 'serenity'];

        $response = Http::timeout(15)
            ->withToken($user->access_token)
            ->get($baseUrl, $params);

        if ($response->failed()) {
            Log::error('[Assets] 资产获取失败', ['status' => $response->status()]);
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
     * 获取类型名称（优先本地数据库，fallback 用 ESI 中文）
     */
    private function getTypeNamesChinese(array $typeIds): array
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
                    } catch (\Exception $ex) {
                        // 跳过
                    }
                }
            }
        }

        return $names;
    }

    private function getTypeDetails(array $typeIds): array
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
                            ->get($baseUrl . "universe/types/{$typeId}/", [
                                'datasource' => 'serenity',
                            ]);
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

    private function getGroupNames(array $groupIds): array
    {
        $names = [];
        $uncached = [];

        foreach ($groupIds as $groupId) {
            $cached = Cache::get("eve_group_zh_{$groupId}");
            if ($cached !== null) {
                $names[$groupId] = $cached;
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
                            ->get($baseUrl . "universe/groups/{$groupId}/", [
                                'datasource' => 'serenity',
                                'language' => 'zh',
                            ]);
                    }
                });

                foreach ($batch as $groupId) {
                    $key = "group_{$groupId}";
                    try {
                        $response = $responses[$key] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $name = $data['name'] ?? '';
                        } else {
                            $name = '';
                        }
                    } catch (\Exception $e) {
                        $name = '';
                    }
                    Cache::put("eve_group_zh_{$groupId}", $name, 86400);
                    $names[$groupId] = $name;
                }
            }
        }

        return $names;
    }
}
