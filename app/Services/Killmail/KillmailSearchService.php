<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;

/**
 * Killmail 搜索服务
 * 
 * 负责自动补全搜索（character/corporation/alliance/ship/system）、
 * 本地搜索、ESI 搜索等功能
 */
class KillmailSearchService
{
    protected EveDataService $eveData;
    protected BetaKbApiClient $kbClient;
    protected string $esiBaseUrl;
    protected string $datasource;

    public function __construct(EveDataService $eveData, BetaKbApiClient $kbClient)
    {
        $this->eveData = $eveData;
        $this->kbClient = $kbClient;
        $this->esiBaseUrl = rtrim(config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/'), '/') . '/';
        $this->datasource = config('esi.datasource', 'serenity');
    }

    /**
     * 统一自动补全接口
     * @param string $query 搜索关键词
     * @param string $type character|corporation|alliance|ship|system
     * @return array [{id, name, type}]
     */
    public function searchAutocomplete(string $query, string $type): array
    {
        $cacheKey = "kb:ac:" . md5("{$type}:{$query}");

        return Cache::remember($cacheKey, 300, function () use ($query, $type) {
            switch ($type) {
                case 'character':
                case 'corporation':
                case 'alliance':
                    return $this->searchKbAutocomplete($query, $type);
                case 'ship':
                    return $this->searchKbShipAutocomplete($query);
                case 'system':
                    $local = $this->searchLocalEntities($query, 'system');
                    if (!empty($local)) return $local;
                    return $this->searchEsiSystems($query);
                default:
                    return [];
            }
        });
    }

    /**
     * KB 自动补全 (character/corporation/alliance)
     */
    protected function searchKbAutocomplete(string $query, string $type): array
    {
        $kbTypeMap = [
            'character' => 'char',
            'corporation' => 'corp',
            'alliance' => 'alliance',
        ];
        $kbType = $kbTypeMap[$type] ?? 'char';

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->kbClient->kbHeaders()['User-Agent'],
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->kbClient->getKbUrl()}/ajax_search/name", [
                'q' => $query,
                't' => $kbType,
            ]);

            if (!$response->ok()) {
                return [];
            }

            $names = $response->json();
            if (!is_array($names) || empty($names)) {
                return [];
            }

            $names = array_slice($names, 0, 15);

            // ESI universe/ids 批量转换为 ID
            $esiResponse = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/', $names);

            if (!$esiResponse->ok()) {
                // 无法获取 ID，仅返回名称
                return array_map(function ($n) use ($type) {
                    return ['id' => 0, 'name' => $n, 'type' => $type];
                }, $names);
            }

            $esiData = $esiResponse->json();
            $results = [];
            $esiKey = $type === 'character' ? 'characters' : ($type === 'corporation' ? 'corporations' : 'alliances');

            foreach ($esiData[$esiKey] ?? [] as $item) {
                if (!empty($item['id']) && !empty($item['name'])) {
                    $results[] = [
                        'id' => (int) $item['id'],
                        'name' => $item['name'],
                        'type' => $type,
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::debug("KB autocomplete failed ({$type}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * 使用 KB API 搜索舰船名称, 并补充本地舰船分组搜索
     */
    protected function searchKbShipAutocomplete(string $query): array
    {
        $results = [];
        $validIds = $this->getValidShipAndStructureIds();

        // 1. 从 KB API 搜索舰船名称
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept' => 'application/json',
                'Referer' => 'https://kb.ceve-market.org/ajax_search/',
            ])->timeout(8)->get('https://kb.ceve-market.org/ajax_search/ship', [
                'q' => $query,
                't' => 'shiptype',
            ]);

            if ($response->ok()) {
                $shipNames = $response->json();
                if (is_array($shipNames) && !empty($shipNames)) {
                    $ids = $this->resolveNamesToIds($shipNames);
                    foreach ($shipNames as $name) {
                        $typeId = $ids[$name] ?? null;
                        // 白名单校验：只允许舰船和建筑
                        if ($typeId && (!empty($validIds) ? isset($validIds[$typeId]) : true)) {
                            $results[] = [
                                'id' => $typeId,
                                'name' => $name,
                                'type' => 'ship',
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // KB API 失败，降级到本地搜索
        }

        // 2. 搜索舰船分组和建筑分组
        $allGroups = array_merge($this->getShipGroups(), $this->getStructureGroups());
        $queryLower = mb_strtolower($query, 'UTF-8');
        $seenGroupNames = [];
        foreach ($allGroups as $group) {
            $gName = $group['name'];
            if (isset($seenGroupNames[$gName])) continue;
            if (mb_strpos(mb_strtolower($gName, 'UTF-8'), $queryLower) !== false) {
                $exists = false;
                foreach ($results as $r) {
                    if ($r['name'] === $gName) { $exists = true; break; }
                }
                if (!$exists) {
                    $results[] = [
                        'id' => $group['group_id'],
                        'name' => $gName,
                        'type' => 'ship_group',
                    ];
                    $seenGroupNames[$gName] = true;
                }
            }
            if (count($results) >= 15) break;
        }

        // 3. 如果 KB API 没返回结果，降级到本地搜索 (使用白名单过滤)
        if (empty($results)) {
            $results = $this->searchLocalEntities($query, 'ship', $validIds);
        }

        return array_slice($results, 0, 15);
    }

    /**
     * 将名称列表转换为 ID (通过 ESI universe/ids)
     */
    public function resolveNamesToIds(array $names): array
    {
        $result = [];
        if (empty($names)) return $result;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'EVE-ESI-Admin/1.0',
                'Accept' => 'application/json',
            ])->timeout(10)->post("{$this->esiBaseUrl}universe/ids/?datasource=serenity", $names);

            if ($response->ok()) {
                $data = $response->json();
                foreach ($data['inventory_types'] ?? [] as $item) {
                    $result[$item['name']] = $item['id'];
                }
            }
        } catch (\Exception $e) {
            // 降级: 从本地数据库查找
            $database = $this->eveData->getItemDatabase();
            $nameMap = array_flip($database);
            foreach ($names as $name) {
                if (isset($nameMap[$name])) {
                    $result[$name] = (int) $nameMap[$name];
                }
            }
        }

        return $result;
    }

    /**
     * 获取所有舰船分组列表 (从 ESI 缓存)
     */
    public function getShipGroups(): array
    {
        return Cache::remember('eve:ship_groups', 86400, function () {
            $groups = [];

            try {
                // ESI 获取 Ship 类别 (category_id=6) 的所有分组
                $catResponse = Http::withHeaders([
                    'User-Agent' => 'EVE-ESI-Admin/1.0',
                ])->timeout(15)->get("{$this->esiBaseUrl}universe/categories/6/?datasource=serenity");

                if (!$catResponse->ok()) return $this->getStaticShipGroups();

                $catData = $catResponse->json();
                $groupIds = $catData['groups'] ?? [];

                // 批量获取分组信息
                $pool = Http::pool(function ($pool) use ($groupIds) {
                    foreach ($groupIds as $gid) {
                        $pool->as("g{$gid}")->withHeaders([
                            'User-Agent' => 'EVE-ESI-Admin/1.0',
                        ])->timeout(10)->get("{$this->esiBaseUrl}universe/groups/{$gid}/?datasource=serenity&language=zh");
                    }
                });

                foreach ($groupIds as $gid) {
                    $resp = $pool["g{$gid}"] ?? null;
                    if ($resp && $resp->ok()) {
                        $gData = $resp->json();
                        $groups[] = [
                            'group_id' => $gid,
                            'name' => $gData['name'] ?? "Group#{$gid}",
                            'type_ids' => $gData['types'] ?? [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                return $this->getStaticShipGroups();
            }

            return !empty($groups) ? $groups : $this->getStaticShipGroups();
        });
    }

    /**
     * 静态舰船分组列表 (ESI 不可用时的降级)
     */
    protected function getStaticShipGroups(): array
    {
        $staticGroups = [
            25 => '护卫舰', 26 => '巡洋舰', 27 => '战列舰', 28 => '工业舰',
            29 => '太空舱', 30 => '泰坦', 31 => '穿梭机', 237 => '货舰',
            324 => '突击护卫舰', 358 => '重型突击巡洋舰', 380 => '深空运输舰',
            381 => '封锁运输舰', 419 => '战列巡洋舰', 420 => '驱逐舰',
            463 => '采矿驳船', 513 => '超级航母', 540 => '指挥舰',
            541 => '拦截舰', 543 => '采掘者', 547 => '航空母舰',
            659 => '后勤巡洋舰', 830 => '隐形轰炸舰', 831 => '电子攻击护卫舰',
            832 => '后勤护卫舰', 833 => '武装工业舰', 834 => '远征护卫舰',
            893 => '侦察舰', 894 => '重型拦截巡洋舰', 898 => '黑隐特勤舰',
            900 => '无畏舰', 906 => '战斗侦察舰', 941 => '工业指挥舰',
            963 => '战略巡洋舰', 1022 => '旗舰工业舰', 1201 => '突击驱逐舰',
            1202 => '指挥驱逐舰', 1283 => '战术驱逐舰',
            1534 => '指挥突击舰', 1538 => '先驱者战列舰', 1972 => '旗舰级战列舰',
        ];

        return array_map(function ($name, $gid) {
            return ['group_id' => $gid, 'name' => $name, 'type_ids' => []];
        }, $staticGroups, array_keys($staticGroups));
    }

    /**
     * 根据舰船分组ID获取该分组下的所有舰船 type_id 列表
     * 
     * @param int $groupId 舰船分组ID (如 237 = 货舰)
     * @return array type_id 数组
     */
    public function getShipGroupTypeIds(int $groupId): array
    {
        $cacheKey = "eve:ship_group_types:{$groupId}";
        
        return Cache::remember($cacheKey, 86400, function () use ($groupId) {
            // 从舰船分组列表中查找
            $groups = $this->getShipGroups();
            
            foreach ($groups as $group) {
                if ((int) $group['group_id'] === $groupId) {
                    $typeIds = $group['type_ids'] ?? [];
                    
                    // 如果已有 type_ids，直接返回
                    if (!empty($typeIds)) {
                        return array_map('intval', $typeIds);
                    }
                    
                    // 否则从 ESI 获取该分组的详细信息
                    break;
                }
            }
            
            // 从 ESI 获取分组详情
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'EVE-ESI-Admin/1.0',
                ])->timeout(15)->get("{$this->esiBaseUrl}universe/groups/{$groupId}/?datasource=serenity&language=zh");
                
                if ($response->ok()) {
                    $data = $response->json();
                    $typeIds = $data['types'] ?? [];
                    
                    if (!empty($typeIds)) {
                        Log::debug("getShipGroupTypeIds: 从 ESI 获取分组 {$groupId} 的舰船列表，共 " . count($typeIds) . " 种");
                        return array_map('intval', $typeIds);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("getShipGroupTypeIds: ESI 获取分组 {$groupId} 失败: " . $e->getMessage());
            }
            
            // 如果 ESI 也失败，尝试静态映射
            $staticTypeIds = $this->getStaticGroupTypeIds($groupId);
            if (!empty($staticTypeIds)) {
                return $staticTypeIds;
            }
            
            Log::warning("getShipGroupTypeIds: 无法获取分组 {$groupId} 的舰船列表");
            return [];
        });
    }

    /**
     * 静态舰船分组 type_id 映射 (ESI 完全不可用时的降级)
     * 包含常见舰船类型的 ID
     */
    protected function getStaticGroupTypeIds(int $groupId): array
    {
        // 常见舰船分组的静态 type_id 映射
        static $staticMap = [
            // 货舰 (237)
            237 => [28, 29, 30, 31, 32, 33, 34, 35, 650, 651, 652, 653],
            // 战列舰 (27)
            27 => [638, 639, 640, 641, 642, 643, 644, 645],
            // 航空母舰 (547)
            547 => [239, 240, 241, 242, 243, 244],
            // 泰坦 (30)
            30 => [671, 672, 673, 674, 675, 676, 1157],
            // 护卫舰 (25)
            25 => [582, 583, 584, 585, 586, 587, 588, 589, 590, 591, 592, 593, 594, 595, 596, 597, 598, 599, 600, 601, 602, 603, 604, 605, 606, 607, 608, 609],
            // 巡洋舰 (26)
            26 => [620, 621, 622, 623, 624, 625, 626, 627, 628, 629, 630, 631, 632, 633, 634, 635, 636, 637],
            // 无畏舰 (900)
            900 => [1972, 1973, 1974, 1975, 1976, 1977, 1978, 1979, 1980],
            // 超级航母 (513)
            513 => [239, 240, 241, 242, 243, 244],
            // 战列巡洋舰 (419)
            419 => [1944, 1945, 1946, 1947, 1948, 1949, 1950, 1951, 1952],
            // 驱逐舰 (420)
            420 => [326, 327, 328, 329, 330, 331, 332, 333, 334, 335],
            // 突击护卫舰 (324)
            324 => [1116, 1117, 1118, 1119, 1120, 1121, 1122, 1123, 1124, 1125],
            // 重型突击巡洋舰 (358)
            358 => [1198, 1199, 1200, 1201, 1202, 1203, 1204, 1205],
            // 战略巡洋舰 (963)
            963 => [2998, 2999, 3000, 3001, 3002, 3003, 3004],
            // 工业舰 (28)
            28 => [340, 341, 342, 343, 344, 345, 346, 347, 348, 349],
            // 采矿驳船 (463)
            463 => [1742, 1743, 1744, 1745, 1746, 1747, 1748],
        ];
        
        return $staticMap[$groupId] ?? [];
    }

    /**
     * 获取建筑分组 (ESI category 65 = Structures)
     */
    public function getStructureGroups(): array
    {
        return Cache::remember('eve:structure_groups', 86400, function () {
            $groups = [];

            try {
                $catResponse = Http::withHeaders([
                    'User-Agent' => 'EVE-ESI-Admin/1.0',
                ])->timeout(15)->get("{$this->esiBaseUrl}universe/categories/65/?datasource=serenity");

                if (!$catResponse->ok()) return $this->getStaticStructureGroups();

                $catData = $catResponse->json();
                $groupIds = $catData['groups'] ?? [];

                $pool = Http::pool(function ($pool) use ($groupIds) {
                    foreach ($groupIds as $gid) {
                        $pool->as("g{$gid}")->withHeaders([
                            'User-Agent' => 'EVE-ESI-Admin/1.0',
                        ])->timeout(10)->get("{$this->esiBaseUrl}universe/groups/{$gid}/?datasource=serenity&language=zh");
                    }
                });

                foreach ($groupIds as $gid) {
                    $resp = $pool["g{$gid}"] ?? null;
                    if ($resp && $resp->ok()) {
                        $gData = $resp->json();
                        $groups[] = [
                            'group_id' => $gid,
                            'name' => $gData['name'] ?? "Group#{$gid}",
                            'type_ids' => $gData['types'] ?? [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                return $this->getStaticStructureGroups();
            }

            return !empty($groups) ? $groups : $this->getStaticStructureGroups();
        });
    }

    /**
     * 静态建筑分组列表 (ESI 不可用时的降级)
     */
    protected function getStaticStructureGroups(): array
    {
        $staticGroups = [
            1657 => '铁壁', // Citadel
            1404 => '工程复合体', // Engineering Complex
            1406 => '精炼厂', // Refinery
            1408 => 'FLEX 建筑', // FLEX structures
            2016 => '主权建筑', // Sovereignty structures
            2017 => '基础设施枢纽', // Infrastructure Hub
        ];

        return array_map(function ($name, $gid) {
            return ['group_id' => $gid, 'name' => $name, 'type_ids' => []];
        }, $staticGroups, array_keys($staticGroups));
    }

    /**
     * 获取所有合法的舰船+建筑 type_id 集合 (白名单)
     */
    public function getValidShipAndStructureIds(): array
    {
        return Cache::remember('eve:valid_ship_structure_ids', 86400, function () {
            $validIds = [];

            foreach ($this->getShipGroups() as $group) {
                foreach ($group['type_ids'] as $typeId) {
                    $validIds[$typeId] = true;
                }
            }

            foreach ($this->getStructureGroups() as $group) {
                foreach ($group['type_ids'] as $typeId) {
                    $validIds[$typeId] = true;
                }
            }

            return $validIds;
        });
    }

    /**
     * 判断 type_id 是否为建筑 (ESI category 65)
     */
    public function isStructureTypeId(int $typeId): bool
    {
        $structureIds = Cache::remember('eve:structure_type_ids', 86400, function () {
            $ids = [];
            foreach ($this->getStructureGroups() as $group) {
                foreach ($group['type_ids'] as $tid) {
                    $ids[$tid] = true;
                }
            }
            return $ids;
        });
        return isset($structureIds[$typeId]);
    }

    /**
     * 本地数据搜索 (ship/system)
     */
    public function searchLocalEntities(string $query, string $category, array $validIds = []): array
    {
        $results = $this->eveData->searchByName($query, $category, 15, $validIds);
        return array_map(function ($item) use ($category) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => $category,
            ];
        }, $results);
    }

    /**
     * 通过 ESI universe/ids 搜索星系（中英文精确匹配）
     */
    public function searchEsiSystems(string $query): array
    {
        $results = [];

        try {
            // 先用中文搜索
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/?datasource=serenity&language=zh', [$query]);

            if ($response->ok()) {
                foreach ($response->json()['systems'] ?? [] as $sys) {
                    $results[] = [
                        'id' => (int) $sys['id'],
                        'name' => $sys['name'],
                        'type' => 'system',
                    ];
                }
            }

            // 如果中文没结果，再尝试英文
            if (empty($results)) {
                $response = Http::timeout(8)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->esiBaseUrl . 'universe/ids/?datasource=serenity&language=en', [$query]);

                if ($response->ok()) {
                    foreach ($response->json()['systems'] ?? [] as $sys) {
                        $results[] = [
                            'id' => (int) $sys['id'],
                            'name' => $sys['name'],
                            'type' => 'system',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("ESI system search failed: " . $e->getMessage());
        }

        return $results;
    }

    // ========================================================
    // 角色搜索 (保留旧接口兼容)
    // ========================================================

    /**
     * 搜索角色
     */
    public function searchCharacter(string $query): array
    {
        return Cache::remember("kb:search:" . md5($query), 300, function () use ($query) {
            $results = [];

            try {
                $results = $this->searchViaKbAndEsi($query);
            } catch (\Exception $e) {
                Log::warning('KB+ESI 搜索失败: ' . $e->getMessage());
            }

            if (empty($results)) {
                try {
                    $results = $this->searchLocalUsers($query);
                } catch (\Exception $e) {
                    // 忽略
                }
            }

            return $results;
        });
    }

    /**
     * 通过 KB + ESI 搜索角色
     */
    protected function searchViaKbAndEsi(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => $this->kbClient->kbHeaders()['User-Agent'],
            'Accept' => 'application/json',
        ])->timeout(10)->get("{$this->kbClient->getKbUrl()}/ajax_search/name", [
            'q' => $query,
            't' => 'char',
        ]);

        if (!$response->ok()) {
            return [];
        }

        $names = $response->json();
        if (!is_array($names) || empty($names)) {
            return [];
        }

        $names = array_slice($names, 0, 10);

        $results = [];
        try {
            $esiResponse = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/', $names);

            if ($esiResponse->ok()) {
                $esiData = $esiResponse->json();
                foreach ($esiData['characters'] ?? [] as $char) {
                    if (!empty($char['id']) && !empty($char['name'])) {
                        $results[] = [
                            'pilot_id' => (int) $char['id'],
                            'name' => $char['name'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('ESI universe/ids 失败: ' . $e->getMessage());
        }

        return $results;
    }

    /**
     * 从本地数据库搜索用户
     */
    protected function searchLocalUsers(string $query): array
    {
        $escapedQuery = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $users = DB::table('users')
            ->where('name', 'like', "%{$escapedQuery}%")
            ->whereNotNull('eve_character_id')
            ->select('eve_character_id', 'name')
            ->limit(20)
            ->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'pilot_id' => (int) $user->eve_character_id,
                'name' => $user->name,
            ];
        }
        return $results;
    }
}
