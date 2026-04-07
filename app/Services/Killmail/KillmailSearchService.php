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
     * 
     * 数据来源: ESI universe/categories/6/groups
     * 最后更新: 2026-04-07
     * 注意: EVE 游戏更新可能新增舰船分组，需定期同步
     */
    protected function getStaticShipGroups(): array
    {
        // 舰船分组ID与名称映射 (来自ESI universe/groups)
        $staticGroups = [
            25 => '轻型护卫舰', 26 => '巡洋舰', 27 => '战列舰', 28 => '工业舰',
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
                Log::debug("getShipGroupTypeIds: ESI 获取分组 {$groupId} 失败: " . $e->getMessage());
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
     * 
     * 数据来源: eve_items.json (根据舰船 category 字段精确匹配)
     * 最后更新: 2026-04-07
     * 注意: 新舰船加入后需同步更新此映射
     */
    protected function getStaticGroupTypeIds(int $groupId): array
    {
        // 常见舰船分组的静态 type_id 映射
        // 注意: 这些ID只包含舰船本身，不含蓝图和涂装
        static $staticMap = [
            // 轻型护卫舰 (25) - T1新手护卫舰
            25 => [588, 596, 601, 606],
            // 巡洋舰 (26) - 包含标准、势力、侦察、后勤、重突、重拦、战略巡洋舰
            26 => [620, 621, 622, 623, 624, 625, 626, 627, 628, 629, 630, 631, 632, 633, 634, 2006,
                   11957, 11959, 11961, 11963, 11965, 11969, 11971, 11978, 11985, 11987, 11989,
                   11993, 11995, 11999, 12003, 12005, 12011, 12013, 12015, 12017, 12019, 12021, 12023,
                   17634, 17709, 17713, 17715, 17718, 17720, 17722, 17843, 17922, 20125,
                   29336, 29337, 29340, 29344, 29984, 29986, 29988, 29990,
                   33470, 33818, 45534, 47270, 49712, 49713, 52252, 54732,
                   62919, 62920, 62921, 62922],
            // 战列舰 (27) - 包含标准、势力、黑隐特勤舰
            27 => [638, 639, 640, 641, 642, 643, 644, 645,
                   17636, 17726, 17728, 17732, 17736, 17738, 17740, 17918, 17920,
                   22428, 22430, 22436, 22440,
                   24688, 24690, 24692, 24694, 28659, 28661, 28665, 28710,
                   32305, 32307, 32309, 32311, 33472, 33820, 47271, 54733, 88001],
            // 工业舰 (28) - T1工业舰
            28 => [340, 341, 342, 343, 344, 345, 346, 347, 348, 349],
            // 泰坦 (30)
            30 => [671, 3764, 11567, 23773, 42126, 42241, 45649, 78576],
            // 穿梭机 (31)
            31 => [672, 11129, 11132, 11134, 21097, 21628],
            // 货舰 (237)
            237 => [20183, 20185, 20187, 20189, 34328, 81040],
            // 突击护卫舰 (324) - T2突击护卫舰
            324 => [11365, 11371, 11379, 11381, 11393, 11400, 12042, 12044, 52250],
            // 重型突击巡洋舰 (358) - T2重型突击巡洋舰
            // 希尔博拉斯级、流浪级、狂热级、伊什塔级、银鹰级、缪宁级、渎圣级、戴默斯级、伊基图萨级
            358 => [11993, 11999, 12003, 12005, 12011, 12015, 12019, 12023, 52252],
            // 深空运输舰 (380) - T2深空运输舰
            380 => [11377, 11383, 11385, 11387, 12046, 12048, 12050, 12052],
            // 封锁运输舰 (381) - T2封锁运输舰
            381 => [11172, 11176, 11184, 11188],
            // 战列巡洋舰 (419) - 包含T1、势力、指挥舰
            419 => [4302, 4306, 4308, 4310, 16227, 16229, 16231, 16233,
                   22442, 22444, 22446, 22448, 22466, 22468, 22470, 22474,
                   24696, 24698, 24700, 24702, 33151, 33153, 33155, 33157, 49711,
                   72811, 72812, 72869, 72872, 78366, 78369, 85086, 89607],
            // 驱逐舰 (420) - 包含T1、拦截、战术驱逐舰、指挥驱逐舰
            420 => [16236, 16238, 16240, 16242,
                   22452, 22456, 22460, 22464,
                   32872, 32874, 32876, 32878,
                   34317, 34562, 34828, 35683,
                   37480, 37481, 37482, 37483,
                   49710, 52254, 73789, 73794, 73795, 73796,
                   78333, 78367, 85087, 89240, 89647, 89649],
            // 采矿驳船 (463)
            463 => [17476, 17478, 17480, 22544, 22546, 22548],
            // 超级航母 (513)
            513 => [23773, 11567, 671],
            // 指挥舰 (540) - T2指挥舰(战列巡洋舰级别)
            540 => [22442, 22444, 22446, 22448, 22466, 22468, 22470, 22474],
            // 拦截舰 (541) - T2拦截驱逐舰
            541 => [22452, 22456, 22460, 22464],
            // 采掘者 (543) - T2采掘采矿驳船
            543 => [22544, 22546, 22548],
            // 航空母舰 (547)
            547 => [3514, 22852, 23757, 23911, 23913, 23915, 23917, 23919, 24483, 42125],
            // 后勤巡洋舰 (659) - T2后勤巡洋舰
            659 => [11987, 11989],
            // 隐形轰炸舰 (830) - T2隐形轰炸舰
            830 => [11373, 11375, 12038, 12040],
            // 电子攻击护卫舰 (831) - T2电子攻击护卫舰
            831 => [11174, 11190, 11194, 11387],
            // 后勤护卫舰 (832) - T2后勤护卫舰
            832 => [37457, 37458, 37459, 37460],
            // 武装工业舰 (833)
            833 => [32872, 32874, 32876, 32878],
            // 远征护卫舰 (834) - T2远征护卫舰
            834 => [11375, 11377, 11379, 11381],
            // 侦察舰 (893) - T2侦察巡洋舰
            893 => [11957, 11959, 11961, 11963, 11965, 11969, 11971, 20125],
            // 重型拦截巡洋舰 (894) - T2重型拦截巡洋舰
            894 => [11995, 12013, 12017, 12021],
            // 黑隐特勤舰 (898) - T2黑隐特勤舰
            898 => [22428, 22430, 22436, 22440],
            // 无畏舰 (900)
            900 => [19720, 19722, 19724, 19726, 42124, 42243, 45647, 52907,
                   73787, 73790, 73792, 73793, 77281, 77283, 77284, 77288, 87381],
            // 战斗侦察舰 (906) - T2战斗侦察舰
            906 => [11959, 11961, 11963, 11969, 11971, 20125],
            // 工业指挥舰 (941)
            941 => [28606, 42244],
            // 战略巡洋舰 (963) - T3战略巡洋舰
            963 => [29984, 29986, 29988, 29990],
            // 旗舰工业舰 (1022)
            1022 => [28606],
            // 突击驱逐舰 (1201) - T2突击驱逐舰
            1201 => [22452, 22456, 22460, 22464],
            // 指挥驱逐舰 (1202) - T2指挥驱逐舰
            1202 => [37480, 37481, 37482, 37483, 52254, 89649],
            // 战术驱逐舰 (1283) - T3战术驱逐舰
            1283 => [34317, 34562, 34828, 35683],
            // 指挥突击舰 (1534)
            1534 => [22446, 22448, 22470, 22474],
            // 先驱者战列舰 (1538)
            1538 => [47271],
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
