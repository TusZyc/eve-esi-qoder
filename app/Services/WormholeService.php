<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Killmail\BetaKbApiClient;

/**
 * 虫洞数据服务
 * 
 * 提供虫洞星系查询、类型查询、异常空间等功能
 */
class WormholeService
{
    /**
     * 静态数据缓存
     */
    private static $wormholeSystems = null;
    private static $wormholeTypes = null;
    private static $wormholeEffects = null;
    private static $wormholeAnomalies = null;
    
    /**
     * Beta KB API 客户端
     */
    private BetaKbApiClient $kbClient;
    
    public function __construct(BetaKbApiClient $kbClient)
    {
        $this->kbClient = $kbClient;
    }
    
    // ========================================================
    // 数据加载方法
    // ========================================================
    
    /**
     * 加载虫洞星系数据
     */
    public static function loadWormholeSystems(): array
    {
        if (self::$wormholeSystems === null) {
            $file = base_path('data/wormhole_systems.json');
            if (file_exists($file)) {
                self::$wormholeSystems = json_decode(file_get_contents($file), true) ?? [];
            } else {
                self::$wormholeSystems = [];
            }
        }
        return self::$wormholeSystems;
    }
    
    /**
     * 加载虫洞类型数据
     */
    public static function loadWormholeTypes(): array
    {
        if (self::$wormholeTypes === null) {
            $file = base_path('data/wormhole_types.json');
            if (file_exists($file)) {
                self::$wormholeTypes = json_decode(file_get_contents($file), true) ?? [];
            } else {
                self::$wormholeTypes = [];
            }
        }
        return self::$wormholeTypes;
    }
    
    /**
     * 加载虫洞效果数据
     */
    public static function loadWormholeEffects(): array
    {
        if (self::$wormholeEffects === null) {
            $file = base_path('data/wormhole_effects.json');
            if (file_exists($file)) {
                self::$wormholeEffects = json_decode(file_get_contents($file), true) ?? [];
            } else {
                self::$wormholeEffects = [];
            }
        }
        return self::$wormholeEffects;
    }
    
    /**
     * 加载虫洞异常空间数据
     */
    public static function loadWormholeAnomalies(): array
    {
        if (self::$wormholeAnomalies === null) {
            $file = base_path('data/wormhole_anomalies.json');
            if (file_exists($file)) {
                self::$wormholeAnomalies = json_decode(file_get_contents($file), true) ?? [];
            } else {
                self::$wormholeAnomalies = [];
            }
        }
        return self::$wormholeAnomalies;
    }
    
    // ========================================================
    // 搜索方法
    // ========================================================
    
    /**
     * 搜索虫洞星系（模糊搜索）
     * 
     * @param string $query 搜索关键词（J编号或系统ID）
     * @param int $limit 返回数量限制
     * @return array 搜索结果
     */
    public function searchSystems(string $query, int $limit = 20): array
    {
        $systems = self::loadWormholeSystems();
        $results = [];
        
        // 标准化查询：去除首尾J，转大写
        $normalizedQuery = strtoupper(preg_replace('/^[Jj]/', '', $query));
        
        foreach ($systems as $name => $system) {
            // 跳过元数据
            if ($name === '_meta') continue;
            
            $systemName = preg_replace('/^[Jj]/', '', $name);
            
            // 匹配系统名称或系统ID
            if (strpos($systemName, $normalizedQuery) !== false ||
                strpos((string)($system['system_id'] ?? ''), $normalizedQuery) !== false) {
                $results[] = [
                    'name' => $name,
                    'system_id' => $system['system_id'],
                    'class' => $system['class'],
                    'effect' => $system['effect'],
                    'effect_zh' => $this->getEffectNameZh($system['effect']),
                ];
                
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 自动补全（用于搜索框）
     */
    public function autocomplete(string $query, int $limit = 10): array
    {
        if (strlen($query) < 1) {
            return [];
        }
        
        return $this->searchSystems($query, $limit);
    }
    
    // ========================================================
    // 详情查询方法
    // ========================================================
    
    /**
     * 获取虫洞星系详情
     * 
     * @param string|int $systemIdentifier 系统名称或ID
     * @return array|null 星系详情
     */
    public function getSystemInfo($systemIdentifier): ?array
    {
        $systems = self::loadWormholeSystems();
        $types = self::loadWormholeTypes();
        $effects = self::loadWormholeEffects();
        $anomalies = self::loadWormholeAnomalies();
        
        // 查找系统
        $system = null;
        $systemName = null;
        
        // 支持多种输入格式
        $searchName = strtoupper($systemIdentifier);
        if (strpos($searchName, 'J') !== 0 && !is_numeric($systemIdentifier)) {
            $searchName = 'J' . $searchName;
        }
        
        // 首先从虫洞数据文件中查找
        foreach ($systems as $name => $sys) {
            if ($name === '_meta') continue;
            
            if ($name === $searchName || ($sys['system_id'] ?? null) == $systemIdentifier) {
                $system = $sys;
                $systemName = $name;
                break;
            }
        }
        
        // 如果没找到，尝试从本地星系列表中查找（动态生成）
        if (!$system) {
            $localSystem = $this->findLocalWormholeSystem($systemIdentifier);
            if ($localSystem) {
                $system = $localSystem;
                $systemName = $localSystem['name'];
            }
        }
        
        if (!$system) {
            return null;
        }
        
        // 获取基础信息
        $systemId = $system['system_id'];
        $class = $system['class'];
        
        // 从本地数据获取星座和星域信息
        $constellationInfo = EveDataService::getLocalConstellationInfo($system['constellation_id'] ?? 0);
        $regionInfo = EveDataService::getLocalRegionName($system['region_id'] ?? 0);
        
        // 获取ESI数据（行星、卫星等）
        $esiData = $this->fetchEsiSystemData($systemId);
        
        // 构建静态连接信息
        $statics = [];
        foreach ($system['statics'] ?? [] as $typeCode) {
            if (isset($types[$typeCode])) {
                $statics[] = [
                    'type' => $typeCode,
                    'destination' => $types[$typeCode]['destination'] ?? null,
                    'destination_zh' => $types[$typeCode]['destination_zh'] ?? $typeCode,
                    'lifetime_hours' => $types[$typeCode]['lifetime_hours'] ?? null,
                    'max_mass' => $types[$typeCode]['max_mass'] ?? null,
                    'jump_mass' => $types[$typeCode]['jump_mass'] ?? null,
                ];
            } else {
                // 类型数据不存在时仍保留编号
                $statics[] = [
                    'type' => $typeCode,
                    'destination' => null,
                    'destination_zh' => $typeCode,
                    'lifetime_hours' => null,
                    'max_mass' => null,
                    'jump_mass' => null,
                ];
            }
        }
        
        // 构建游走虫洞信息（根据虫洞类型的 source 字段动态匹配）
        $wandering = $this->getWanderingWormholes($system['class_name'] ?? ('c' . $class), $types);
        
        // 获取效果详情
        $effectDetail = null;
        if ($system['effect'] && isset($effects[$system['effect']])) {
            $effectDetail = $effects[$system['effect']];
        }
        
        // 获取异常空间（按类型和等级提取）
        $classAnomalies = [];
        foreach ($anomalies as $type => $classSites) {
            if (isset($classSites[(string)$class])) {
                $classAnomalies[$type] = $classSites[(string)$class];
            }
        }
        
        return [
            'name' => $systemName,
            'system_id' => $systemId,
            'class' => $class,
            'class_name' => $system['class_name'] ?? ('C' . $class),
            'effect' => $system['effect'],
            'effect_zh' => $this->getEffectNameZh($system['effect']),
            'effect_detail' => $effectDetail,
            'constellation_id' => $system['constellation_id'] ?? null,
            'constellation_name' => $constellationInfo['name'] ?? null,
            'region_id' => $system['region_id'] ?? null,
            'region_name' => $regionInfo,
            'security' => -0.99,
            'statics' => $statics,
            'wandering' => $wandering,
            'planets' => $esiData['planets'] ?? [],
            'system_radius_au' => $esiData['system_radius_au'] ?? null,
            'anomalies' => $classAnomalies,
        ];
    }
    
    /**
     * 从ESI获取系统数据（行星、卫星、系统大小）
     */
    private function fetchEsiSystemData(int $systemId): array
    {
        $cacheKey = "wormhole:esi:system:{$systemId}";
        
        return Cache::remember($cacheKey, 86400, function() use ($systemId) {
            try {
                $baseUrl = rtrim(config('esi.base_url'), '/');
                $datasource = config('esi.datasource', 'serenity');
                
                $response = Http::timeout(10)->get(
                    "{$baseUrl}/universe/systems/{$systemId}/?datasource={$datasource}&language=zh"
                );
                
                if (!$response->ok()) {
                    return ['planets' => [], 'system_radius_au' => null];
                }
                
                $data = $response->json();
                
                // 计算系统大小（基于行星位置）
                $maxDistance = 0;
                $planets = [];
                
                foreach ($data['planets'] ?? [] as $planet) {
                    $planetInfo = [
                        'planet_id' => $planet['planet_id'],
                        'name' => $planet['name'] ?? null,
                        'moons' => [],
                    ];
                    
                    // 添加卫星信息
                    foreach ($planet['moons'] ?? [] as $moon) {
                        $planetInfo['moons'][] = [
                            'moon_id' => $moon['moon_id'] ?? $moon,
                            'name' => $moon['name'] ?? null,
                        ];
                    }
                    
                    $planets[] = $planetInfo;
                    
                    // 计算距离
                    if (isset($planet['position'])) {
                        $distance = sqrt(
                            pow($planet['position']['x'], 2) +
                            pow($planet['position']['y'], 2) +
                            pow($planet['position']['z'], 2)
                        );
                        $maxDistance = max($maxDistance, $distance);
                    }
                }
                
                // 转换为AU (1 AU ≈ 149,597,870,700 米)
                $radiusAu = $maxDistance > 0 ? round($maxDistance / 149597870700, 2) : null;
                
                return [
                    'planets' => $planets,
                    'system_radius_au' => $radiusAu,
                ];
                
            } catch (\Exception $e) {
                Log::debug("ESI系统数据获取失败: {$systemId} - " . $e->getMessage());
                return ['planets' => [], 'system_radius_au' => null];
            }
        });
    }
    
    /**
     * 获取击杀报告
     */
    public function getSystemKills(int $systemId, int $limit = 5): array
    {
        try {
            // 使用 Beta KB API 搜索该系统的击杀
            $params = [
                'systems' => [$systemId],
            ];
            
            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($params);
            
            // 按时间排序，取最近的 $limit 条
            usort($kills, function($a, $b) {
                return ($b['kill_timestamp'] ?? 0) - ($a['kill_timestamp'] ?? 0);
            });
            
            $recentKills = array_slice($kills, 0, $limit);
            
            return [
                'kills' => $recentKills,
                'total' => count($kills),
                'kb_url' => "https://kb.ceve-market.org/system/{$systemId}",
            ];
            
        } catch (\Exception $e) {
            Log::debug("击杀数据获取失败: {$systemId} - " . $e->getMessage());
            return [
                'kills' => [],
                'total' => 0,
                'kb_url' => "https://kb.ceve-market.org/system/{$systemId}",
            ];
        }
    }
    
    // ========================================================
    // 列表查询方法
    // ========================================================
    
    /**
     * 获取虫洞星系列表（分页）
     */
    public function getSystemsList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $systems = self::loadWormholeSystems();
        $types = self::loadWormholeTypes();
        
        // 应用筛选
        $filtered = [];
        foreach ($systems as $name => $system) {
            // 跳过元数据
            if ($name === '_meta') continue;
            
            // 按等级筛选
            if (isset($filters['class']) && $system['class'] != $filters['class']) {
                continue;
            }
            
            // 按效果筛选
            if (isset($filters['effect']) && $system['effect'] != $filters['effect']) {
                continue;
            }
            
            $filtered[] = [
                'name' => $name,
                'system_id' => $system['system_id'],
                'class' => $system['class'],
                'class_name' => 'C' . $system['class'],
                'effect' => $system['effect'],
                'effect_zh' => $this->getEffectNameZh($system['effect']),
                'statics' => $this->formatStatics($system['statics'] ?? [], $types),
            ];
        }
        
        // 排序
        usort($filtered, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        // 分页
        $total = count($filtered);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($filtered, $offset, $perPage);
        
        return [
            'systems' => $paged,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ];
    }
    
    /**
     * 获取虫洞类型列表
     */
    public function getTypesList(?string $destination = null): array
    {
        $types = self::loadWormholeTypes();
        
        $result = [];
        foreach ($types as $code => $type) {
            // 按目的地筛选
            if ($destination && ($type['destination'] ?? '') !== $destination) {
                continue;
            }
            
            $result[] = [
                'type' => $code,
                'type_id' => $type['type_id'] ?? null,
                'destination' => $type['destination'] ?? null,
                'destination_zh' => $type['destination_zh'] ?? $code,
                'source' => $type['source'] ?? [],
                'is_static' => $type['is_static'] ?? false,
                'lifetime_hours' => $type['lifetime_hours'] ?? null,
                'max_mass' => $type['max_mass'] ?? null,
                'jump_mass' => $type['jump_mass'] ?? null,
                'mass_regen' => $type['mass_regen'] ?? 0,
            ];
        }
        
        return $result;
    }
    
    // ========================================================
    // 辅助方法
    // ========================================================
    
    /**
     * 获取效果中文名称
     */
    private function getEffectNameZh(?string $effect): ?string
    {
        if (!$effect) {
            return null;
        }
        
        $effects = self::loadWormholeEffects();
        return $effects[$effect]['name_zh'] ?? null;
    }
    
    /**
     * 格式化静态连接信息
     */
    private function formatStatics(array $statics, array $types): array
    {
        $result = [];
        foreach ($statics as $typeCode) {
            if (isset($types[$typeCode])) {
                $result[] = [
                    'type' => $typeCode,
                    'destination_zh' => $types[$typeCode]['destination_zh'],
                ];
            }
        }
        return $result;
    }
    
    /**
     * 获取所有虫洞效果列表（用于筛选）
     */
    public static function getEffectsList(): array
    {
        $effects = self::loadWormholeEffects();
        $result = [];
        
        foreach ($effects as $key => $effect) {
            $result[] = [
                'key' => $key,
                'name_zh' => $effect['name_zh'] ?? $key,
                'name_en' => $effect['name_en'] ?? $key,
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取游走虫洞列表（根据虫洞类型的source字段动态匹配）
     */
    private function getWanderingWormholes(string $className, array $types): array
    {
        $wandering = [];
        foreach ($types as $code => $type) {
            // 游走虫洞：非静态，且source包含当前等级
            $sources = $type['source'] ?? [];
            $isStatic = $type['is_static'] ?? false;
            if (!$isStatic && in_array($className, $sources)) {
                $wandering[] = [
                    'type' => $code,
                    'destination' => $type['destination'] ?? null,
                    'destination_zh' => $type['destination_zh'] ?? $code,
                ];
            }
        }
        return $wandering;
    }
    
    /**
     * 从本地星系列表中查找虫洞星系（动态生成）
     * 
     * @param string|int $identifier 系统名称或ID
     * @return array|null
     */
    private function findLocalWormholeSystem($identifier): ?array
    {
        $systemsData = EveDataService::loadLocalSystems();
        if (empty($systemsData)) {
            return null;
        }
        
        // 标准化搜索名称
        $searchName = is_numeric($identifier) ? null : strtoupper($identifier);
        $searchId = is_numeric($identifier) ? (int)$identifier : null;
        
        // 虫洞系统ID范围：31000001-31002605
        foreach ($systemsData as $id => $sys) {
            $id = (int)$id;
            if ($id < 31000001 || $id > 31002700) {
                continue;
            }
            
            // 匹配名称或ID
            if ($searchName && strtoupper($sys['name']) !== $searchName) {
                continue;
            }
            if ($searchId && $id !== $searchId) {
                continue;
            }
            
            // 找到了，根据区域ID推断等级
            $regionId = $sys['region_id'] ?? 0;
            $class = $this->inferClassFromRegion($regionId);
            
            // 根据等级分配静态连接
            $statics = $this->inferStaticsFromClass($class);
            
            return [
                'system_id' => $id,
                'name' => $sys['name'],
                'constellation_id' => $sys['constellation_id'] ?? null,
                'region_id' => $regionId,
                'class' => $class,
                'class_name' => 'c' . $class,
                'effect' => null,
                'statics' => $statics,
            ];
        }
        
        return null;
    }
    
    /**
     * 根据区域ID推断虫洞等级
     */
    private function inferClassFromRegion(int $regionId): int
    {
        // 区域命名规则：
        // 11000001-11000003: A区域 (C1)
        // 11000004-11000008: B区域 (C2)
        // 11000009-11000015: C区域 (C3)
        // 11000016-11000023: D区域 (C4)
        // 11000024-11000029: E区域 (C5)
        // 11000030: F区域 (C6)
        // 11000031: G区域 (小型虫洞)
        // 11000032: H区域
        // 11000033: K区域
        
        if ($regionId >= 11000001 && $regionId <= 11000003) return 1;
        if ($regionId >= 11000004 && $regionId <= 11000008) return 2;
        if ($regionId >= 11000009 && $regionId <= 11000015) return 3;
        if ($regionId >= 11000016 && $regionId <= 11000023) return 4;
        if ($regionId >= 11000024 && $regionId <= 11000029) return 5;
        if ($regionId === 11000030) return 6;
        if ($regionId === 11000031) return 13; // Thera
        if ($regionId === 11000033) return 13; // K-space wormholes
        
        return 1; // 默认
    }
    
    /**
     * 根据等级推断静态连接类型
     */
    private function inferStaticsFromClass(int $class): array
    {
        // 根据虫洞等级规则分配静态连接
        $staticRules = [
            1 => ['N110'], // C1: 一个静态通向已知空间
            2 => ['B274', 'Z647'], // C2: 两个静态（一个K空间 + 一个J空间）
            3 => ['U210'], // C3: 一个静态通向已知空间
            4 => ['C247', 'X877'], // C4: 两个静态通向J空间
            5 => ['H296'], // C5: 一个静态通向J空间
            6 => ['W237'], // C6: 一个静态通向J空间
            13 => [], // Thera等特殊系统
        ];
        
        return $staticRules[$class] ?? [];
    }
}