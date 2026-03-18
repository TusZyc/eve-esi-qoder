<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketService
{
    private string $baseUrl;
    private string $datasource;
    private EveDataService $eveDataService;

    public function __construct(EveDataService $eveDataService)
    {
        $this->baseUrl = config('esi.base_url');
        $this->datasource = config('esi.datasource', 'serenity');
        $this->eveDataService = $eveDataService;
    }

    /**
     * 获取市场分组树（cache-through 模式：缓存不存在时自动从 ESI 获取）
     */
    public function getMarketGroupsTree(): array
    {
        return Cache::remember('market_groups_tree', 86400 * 7, function () {
            Log::info('Market groups tree cache miss, rebuilding from ESI...');
            return $this->buildMarketGroupsTreeFromApi();
        });
    }

    /**
     * 获取所有星域列表（cache-through 模式：缓存不存在时自动从 ESI 获取）
     */
    public function getAllRegions(): array
    {
        return Cache::remember('market_regions_list', 86400 * 7, function () {
            Log::info('Market regions list cache miss, rebuilding from ESI...');
            return $this->fetchAllRegionsFromApi();
        });
    }

    /**
     * 从 ESI API 获取并构建市场分组树
     */
    private function buildMarketGroupsTreeFromApi(): array
    {
        try {
            // 获取所有市场分组 ID
            $response = Http::timeout(15)->get($this->baseUrl . 'markets/groups/', [
                'datasource' => $this->datasource,
                'language' => 'zh',
            ]);

            if (!$response->ok()) {
                Log::error('Failed to fetch market groups: ' . $response->status());
                return [];
            }

            $allGroupIds = $response->json();
            if (empty($allGroupIds)) {
                return [];
            }

            // 批量获取分组详情
            $details = [];
            $batchSize = 50;
            $batches = array_chunk($allGroupIds, $batchSize);

            foreach ($batches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch) {
                    foreach ($batch as $id) {
                        $pool->as("group_{$id}")
                            ->timeout(10)
                            ->get($this->baseUrl . "markets/groups/{$id}/", [
                                'datasource' => $this->datasource,
                                'language' => 'zh',
                            ]);
                    }
                });

                foreach ($batch as $id) {
                    $r = $responses["group_{$id}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $d = $r->json();
                        $details[$id] = [
                            'id' => $d['market_group_id'],
                            'name' => $d['name'],
                            'parent_id' => $d['parent_group_id'] ?? null,
                            'types' => $d['types'] ?? [],
                            'children' => [],
                        ];
                    }
                }

                usleep(100000); // 100ms delay between batches
            }

            // 加载物品名称数据库
            $itemDb = $this->eveDataService->getItemDatabase();

            // 为叶子分组的 types 附加名称
            foreach ($details as $id => &$group) {
                if (!empty($group['types']) && is_array($group['types'])) {
                    $namedTypes = [];
                    foreach ($group['types'] as $typeId) {
                        if (is_numeric($typeId)) {
                            $name = $itemDb[(int)$typeId] ?? null;
                            $namedTypes[] = [
                                'id' => (int)$typeId,
                                'name' => $name ?? ('物品#' . $typeId),
                            ];
                        }
                    }
                    $group['types'] = $namedTypes;
                }
            }
            unset($group);

            // 构建树结构
            $tree = [];
            $lookup = [];
            foreach ($details as $id => $group) {
                $lookup[$id] = $group;
            }
            foreach ($lookup as $id => &$group) {
                if ($group['parent_id'] === null) {
                    $tree[] = &$group;
                } elseif (isset($lookup[$group['parent_id']])) {
                    $lookup[$group['parent_id']]['children'][] = &$group;
                }
            }
            usort($tree, fn($a, $b) => $a['name'] <=> $b['name']);

            Log::info('Market groups tree rebuilt successfully: ' . count($tree) . ' top-level groups');
            return $tree;

        } catch (\Exception $e) {
            Log::error('Failed to build market groups tree: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 从 ESI API 获取所有星域列表
     */
    private function fetchAllRegionsFromApi(): array
    {
        try {
            $regionResp = Http::timeout(15)->get($this->baseUrl . 'universe/regions/', [
                'datasource' => $this->datasource,
            ]);

            if (!$regionResp->ok()) {
                Log::error('Failed to fetch regions: ' . $regionResp->status());
                return [];
            }

            $regionIds = $regionResp->json();
            if (empty($regionIds)) {
                return [];
            }

            $regions = [];
            $regionBatches = array_chunk($regionIds, 30);

            foreach ($regionBatches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch) {
                    foreach ($batch as $rid) {
                        $pool->as("region_{$rid}")
                            ->timeout(10)
                            ->get($this->baseUrl . "universe/regions/{$rid}/", [
                                'datasource' => $this->datasource,
                                'language' => 'zh',
                            ]);
                    }
                });

                foreach ($batch as $rid) {
                    $r = $responses["region_{$rid}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $d = $r->json();
                        $regions[] = [
                            'id' => $d['region_id'],
                            'name' => $d['name'],
                        ];
                    }
                }

                usleep(100000); // 100ms delay between batches
            }

            usort($regions, fn($a, $b) => $a['name'] <=> $b['name']);
            Log::info('Market regions list rebuilt successfully: ' . count($regions) . ' regions');
            return $regions;

        } catch (\Exception $e) {
            Log::error('Failed to fetch regions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 搜索物品（模糊匹配，含分类路径）
     */
    public function searchItems(string $query, int $limit = 50): array
    {
        $results = $this->eveDataService->searchByName($query, 'item', $limit);
        $categoryMap = $this->buildTypeCategoryMap();

        foreach ($results as &$item) {
            $item['category'] = $categoryMap[$item['id']] ?? '';
        }

        return $results;
    }

    /**
     * 获取区域内有活跃订单的物品 ID 列表
     */
    public function getActiveTypeIds($regionId): array
    {
        if ($regionId === 'all' || $regionId === 0 || $regionId === '0') {
            return $this->getAllActiveTypeIds();
        }

        $regionId = (int) $regionId;
        return Cache::remember("market_active_types_{$regionId}", 300, function () use ($regionId) {
            try {
                Log::info("获取区域 {$regionId} 的活跃物品类型...");
                $r = Http::timeout(15)->get($this->baseUrl . "markets/{$regionId}/types/", [
                    'datasource' => $this->datasource,
                    'page' => 1,
                ]);
                if (!$r->ok()) {
                    Log::warning("获取区域 {$regionId} 活跃物品类型失败: HTTP " . $r->status());
                    return [];
                }

                $allTypes = $r->json() ?: [];
                $totalPages = (int) ($r->header('X-Pages') ?? 1);
                Log::info("区域 {$regionId} 共 {$totalPages} 页，第一页获取到 " . count($allTypes) . " 个物品类型");

                if ($totalPages > 1) {
                    $responses = Http::pool(function ($pool) use ($regionId, $totalPages) {
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $pool->as("page_{$page}")->timeout(15)
                                ->get($this->baseUrl . "markets/{$regionId}/types/", [
                                    'datasource' => $this->datasource,
                                    'page' => $page,
                                ]);
                        }
                    });

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $resp = $responses["page_{$page}"] ?? null;
                        if ($resp instanceof \Illuminate\Http\Client\Response && $resp->ok()) {
                            $types = $resp->json();
                            if (is_array($types)) {
                                $allTypes = array_merge($allTypes, $types);
                            }
                        }
                    }
                }

                Log::info("区域 {$regionId} 总计获取到 " . count($allTypes) . " 个活跃物品类型");
                return $allTypes;
            } catch (\Exception $e) {
                Log::error("获取活跃物品类型失败 (region={$regionId}): " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * 获取所有贸易中心的活跃物品 ID 合集
     */
    private function getAllActiveTypeIds(): array
    {
        return Cache::remember('market_active_types_all', 300, function () {
            $tradeHubs = config('market.trade_hub_regions', [10000002]);
            $allTypes = [];
            foreach ($tradeHubs as $regionId) {
                $types = $this->getActiveTypeIds($regionId);
                $allTypes = array_merge($allTypes, $types);
            }
            return array_values(array_unique($allTypes));
        });
    }

    /**
     * 构建物品ID到分类路径的映射
     */
    public function buildTypeCategoryMap(): array
    {
        return Cache::remember('market_type_category_map', 3600, function () {
            $tree = $this->getMarketGroupsTree();
            $map = [];
            $this->traverseTreeForCategories($tree, [], $map);
            return $map;
        });
    }

    private function traverseTreeForCategories(array $nodes, array $path, array &$map): void
    {
        foreach ($nodes as $node) {
            $currentPath = array_merge($path, [$node['name']]);

            if (!empty($node['types'])) {
                $pathStr = implode(' > ', $currentPath);
                foreach ($node['types'] as $type) {
                    $typeId = is_array($type) ? $type['id'] : $type;
                    $map[$typeId] = $pathStr;
                }
            }

            if (!empty($node['children'])) {
                $this->traverseTreeForCategories($node['children'], $currentPath, $map);
            }
        }
    }

    /**
     * 为订单计算到期时间
     */
    public function enrichOrdersWithExpires(array $orders): array
    {
        foreach ($orders as &$order) {
            if (isset($order['issued']) && isset($order['duration'])) {
                try {
                    $issued = new \DateTime($order['issued']);
                    $issued->modify('+' . (int)$order['duration'] . ' days');
                    $order['expires'] = $issued->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                    $order['expires'] = null;
                }
            }
        }
        return $orders;
    }

    /**
     * 获取市场订单（支持单星域和"全部"模式）
     */
    public function getOrders($regionId, int $typeId): array
    {
        if ($regionId === 'all' || $regionId === 0 || $regionId === '0') {
            return $this->getAllRegionOrders($typeId);
        }

        $regionId = (int) $regionId;
        return Cache::remember("market_orders_{$regionId}_{$typeId}", 300, function () use ($regionId, $typeId) {
            try {
                Log::info("获取订单: region={$regionId}, type_id={$typeId}");
                $r = Http::timeout(15)->get($this->baseUrl . "markets/$regionId/orders/", [
                    'datasource' => $this->datasource, 'type_id' => $typeId, 'page' => 1]);
                if (!$r->ok()) {
                    Log::warning("获取订单失败: region={$regionId}, type_id={$typeId}, status=" . $r->status());
                    return ['sell' => [], 'buy' => []];
                }
                $orders = $r->json();
                if (!is_array($orders)) return ['sell' => [], 'buy' => []];

                $totalPages = (int) ($r->header('X-Pages') ?? 1);
                Log::info("订单分页: 共 {$totalPages} 页，第一页 " . count($orders) . " 条");

                // 获取所有页的数据
                if ($totalPages > 1) {
                    $responses = Http::pool(function ($pool) use ($regionId, $typeId, $totalPages) {
                        for ($page = 2; $page <= $totalPages; $page++) {
                            $pool->as("page_{$page}")->timeout(15)
                                ->get($this->baseUrl . "markets/$regionId/orders/", [
                                    'datasource' => $this->datasource,
                                    'type_id' => $typeId,
                                    'page' => $page,
                                ]);
                        }
                    });

                    for ($page = 2; $page <= $totalPages; $page++) {
                        $resp = $responses["page_{$page}"] ?? null;
                        if ($resp instanceof \Illuminate\Http\Client\Response && $resp->ok()) {
                            $pageOrders = $resp->json();
                            if (is_array($pageOrders)) {
                                $orders = array_merge($orders, $pageOrders);
                            }
                        }
                    }
                }

                Log::info("订单总数: " . count($orders) . " 条");

                $sell = []; $buy = [];
                foreach ($orders as $o) {
                    if (!is_array($o)) continue;
                    if ($o['is_buy_order'] ?? false) $buy[] = $o; else $sell[] = $o;
                }
                usort($sell, fn($a, $b) => $a['price'] <=> $b['price']);
                usort($buy, fn($a, $b) => $b['price'] <=> $a['price']);
                return ['sell' => $sell, 'buy' => $buy];
            } catch (\Exception $e) {
                Log::error('获取订单失败: ' . $e->getMessage());
                return ['sell' => [], 'buy' => []];
            }
        });
    }

    /**
     * 查询主要贸易中心的合并订单
     */
    private function getAllRegionOrders(int $typeId): array
    {
        return Cache::remember("market_orders_all_{$typeId}", 300, function () use ($typeId) {
            $tradeHubs = config('market.trade_hub_regions', [10000002, 10000043, 10000030, 10000032, 10000042]);

            try {
                $responses = Http::pool(function ($pool) use ($tradeHubs, $typeId) {
                    foreach ($tradeHubs as $regionId) {
                        $pool->as("region_{$regionId}")->timeout(15)
                            ->get($this->baseUrl . "markets/$regionId/orders/", [
                                'datasource' => $this->datasource, 'type_id' => $typeId, 'page' => 1,
                            ]);
                    }
                });

                $sell = []; $buy = [];
                foreach ($tradeHubs as $regionId) {
                    $r = $responses["region_{$regionId}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $orders = $r->json();
                        if (!is_array($orders)) continue;
                        foreach ($orders as $o) {
                            if (!is_array($o)) continue;
                            if ($o['is_buy_order'] ?? false) $buy[] = $o; else $sell[] = $o;
                        }
                    }
                }

                usort($sell, fn($a, $b) => $a['price'] <=> $b['price']);
                usort($buy, fn($a, $b) => $b['price'] <=> $a['price']);
                return ['sell' => $sell, 'buy' => $buy];
            } catch (\Exception $e) {
                Log::error('获取全部星域订单失败: ' . $e->getMessage());
                return ['sell' => [], 'buy' => []];
            }
        });
    }

    public function getPriceHistory($regionId, int $typeId): array
    {
        if ($regionId === 'all' || $regionId === 0 || $regionId === '0') {
            $regionId = 10000002; // 全部模式下价格历史使用 The Forge
        }
        $regionId = (int) $regionId;

        return Cache::remember("market_history_{$regionId}_{$typeId}", 3600, function () use ($regionId, $typeId) {
            try {
                $r = Http::timeout(15)->get($this->baseUrl . "markets/$regionId/history/", [
                    'datasource' => $this->datasource, 'type_id' => $typeId]);
                return $r->ok() ? ($r->json() ?: []) : [];
            } catch (\Exception $e) { return []; }
        });
    }

    public function getTypeDetail(int $typeId): ?array
    {
        return Cache::remember("type_detail_$typeId", 86400, function () use ($typeId) {
            try {
                $r = Http::timeout(10)->get($this->baseUrl . "universe/types/$typeId/", [
                    'datasource' => $this->datasource, 'language' => 'zh']);
                return $r->ok() ? $r->json() : null;
            } catch (\Exception $e) { return null; }
        });
    }

    public function getCharacterOrders(string $token, int $charId): array
    {
        // 不使用缓存，每次都从 ESI 获取最新数据
        // 避免 Token 过期后缓存错误结果
        try {
            $r = Http::timeout(15)->withToken($token)
                ->get($this->baseUrl . "characters/$charId/orders/", ['datasource' => $this->datasource]);
            
            if (!$r->ok()) {
                Log::warning("获取角色订单失败: charId=$charId, status=" . $r->status());
                return [];
            }
            
            return $r->json() ?: [];
        } catch (\Exception $e) {
            Log::error("获取角色订单异常: charId=$charId, error=" . $e->getMessage());
            return [];
        }
    }

    /**
     * 为订单添加中文位置名称（参考 AssetDataController 的逐段翻译机制）
     */
    public function enrichOrdersWithLocation(array $orders): array
    {
        if (empty($orders)) return $orders;

        // 收集需要解析的 location_id
        $locationIds = [];
        foreach ($orders as $o) {
            if (isset($o['location_id'])) $locationIds[$o['location_id']] = true;
        }

        // 批量解析位置（含中文翻译）
        $locationInfo = $this->resolveLocationsChineseNames(array_keys($locationIds));

        // 设置 location_name
        foreach ($orders as &$order) {
            $locId = $order['location_id'] ?? 0;
            $order['location_name'] = $locationInfo[$locId]['name'] ?? (string) $locId;
        }

        return $orders;
    }

    /**
     * 批量解析位置为中文名（空间站逐段翻译，与资产页面一致）
     */
    private function resolveLocationsChineseNames(array $locationIds): array
    {
        $info = [];
        $uncachedStations = [];

        foreach ($locationIds as $id) {
            // 优先使用与资产页面相同的缓存键
            $cached = Cache::get("eve_locinfo_{$id}");
            if ($cached !== null) {
                $info[$id] = $cached;
                continue;
            }

            if ($id >= 1000000000000) {
                // 玩家建筑，公开 API 无法查询
                $locInfo = ['name' => '玩家建筑', 'system_id' => 0];
                $info[$id] = $locInfo;
                Cache::put("eve_locinfo_{$id}", $locInfo, 86400);
            } else {
                // NPC 空间站：先查本地数据库
                $localName = $this->eveDataService->getNameById($id);
                $stationSysMap = $this->eveDataService->getStationSystemMap();
                $localSysId = $stationSysMap[(int) $id] ?? null;
                if ($localName && $localSysId && !str_starts_with($localName, '未知')) {
                    $locInfo = ['name' => $localName, 'system_id' => (int) $localSysId];
                    $info[$id] = $locInfo;
                    Cache::put("eve_locinfo_{$id}", $locInfo, 86400);
                } else {
                    $uncachedStations[] = $id;
                }
            }
        }

        // 对未缓存的 NPC 空间站执行逐段翻译
        if (!empty($uncachedStations)) {
            $this->translateStationNames($uncachedStations, $info);
        }

        return $info;
    }

    /**
     * 空间站英文名逐段翻译为中文（星系名 + Moon + 军团名 + 设施类型）
     */
    private function translateStationNames(array $stationIds, array &$info): void
    {
        // 1) 获取空间站详情（name, system_id, owner）
        $stationData = [];
        $batches = array_chunk($stationIds, 5);
        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch) {
                foreach ($batch as $stationId) {
                    $pool->as("station_{$stationId}")
                        ->timeout(5)
                        ->get($this->baseUrl . "universe/stations/{$stationId}/", [
                            'datasource' => $this->datasource,
                        ]);
                }
            });
            foreach ($batch as $stationId) {
                try {
                    $response = $responses["station_{$stationId}"] ?? null;
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

        // 2) 获取中英文星系名
        $sysIds = array_values(array_unique(array_filter(array_column($stationData, 'system_id'))));
        $zhSysNames = [];
        $enSysNames = [];
        if (!empty($sysIds)) {
            $sysBatches = array_chunk($sysIds, 5);
            foreach ($sysBatches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch) {
                    foreach ($batch as $sysId) {
                        $pool->as("zh_{$sysId}")->timeout(5)
                            ->get($this->baseUrl . "universe/systems/{$sysId}/", ['datasource' => $this->datasource, 'language' => 'zh']);
                        $pool->as("en_{$sysId}")->timeout(5)
                            ->get($this->baseUrl . "universe/systems/{$sysId}/", ['datasource' => $this->datasource, 'language' => 'en']);
                    }
                });
                foreach ($batch as $sysId) {
                    try {
                        $r = $responses["zh_{$sysId}"] ?? null;
                        if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                            $zhSysNames[$sysId] = $r->json()['name'] ?? '';
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
            try {
                $resp = Http::timeout(10)->post($this->baseUrl . 'universe/names/', $ownerIds);
                if ($resp->ok()) {
                    foreach ($resp->json() as $item) {
                        $zhCorpNames[$item['id']] = $item['name'];
                    }
                }
            } catch (\Exception $e) {}

            $corpBatches = array_chunk($ownerIds, 5);
            foreach ($corpBatches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch) {
                    foreach ($batch as $corpId) {
                        $pool->as("corp_{$corpId}")->timeout(5)
                            ->get($this->baseUrl . "corporations/{$corpId}/", ['datasource' => $this->datasource]);
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

            // 替换星系名（英->中）
            $enSys = $enSysNames[$sysId] ?? '';
            $zhSys = $zhSysNames[$sysId] ?? '';
            if ($enSys && $zhSys && str_starts_with($name, $enSys)) {
                $name = $zhSys . substr($name, strlen($enSys));
            }

            // Moon -> 卫星
            $name = preg_replace('/\bMoon\b/', '卫星', $name);

            // 替换军团名（英->中）
            $enCorp = $enCorpNames[$ownerId] ?? '';
            $zhCorp = $zhCorpNames[$ownerId] ?? '';
            if ($enCorp && $zhCorp) {
                $name = str_replace($enCorp, $zhCorp, $name);
            }

            // 替换设施类型（英->中）
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

    /**
     * 为角色订单附加物品名称
     */
    public function enrichOrdersWithTypeName(array $orders): array
    {
        if (empty($orders)) return $orders;

        $typeIds = array_unique(array_column($orders, 'type_id'));
        $typeNames = $this->eveDataService->getNamesByIds($typeIds);

        foreach ($orders as &$order) {
            $order['type_name'] = $typeNames[$order['type_id']] ?? ('物品#' . $order['type_id']);
        }

        return $orders;
    }
}
