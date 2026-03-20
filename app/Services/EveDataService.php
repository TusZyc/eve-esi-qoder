<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * EVE 数据服务 - 统一的 ID 转名称服务
 * 
 * 使用本地 JSON 静态数据优先，API 兜底
 */
class EveDataService
{
    /**
     * 数据文件路径
     */
    private $dataPath;
    
    /**
     * 站点→星系映射文件路径
     */
    private $stationSystemPath;
    
    /**
     * 数据元信息路径
     */
    private $metaPath;
    
    /**
     * 物品数据库缓存
     */
    private $itemDatabase = null;
    
    /**
     * 站点→星系映射缓存
     */
    private $stationSystemMap = null;
    
    // ========================================================
    // 本地静态数据缓存 (使用 static 变量，同一请求周期只加载一次)
    // ========================================================
    
    private static $localRegions = null;         // 星域数据
    private static $localConstellations = null;  // 星座数据
    private static $localSystems = null;         // 星系数据
    private static $localStations = null;        // NPC空间站数据
    private static $localStructures = null;      // 玩家建筑数据
    private static $localItems = null;           // 物品数据
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->dataPath = base_path('data/eve_names.json');
        $this->stationSystemPath = base_path('data/eve_station_systems.json');
        $this->metaPath = base_path('data/evedata_meta.json');
    }
    
    // ========================================================
    // 本地静态数据加载方法
    // ========================================================
    
    /**
     * 加载本地静态数据
     * 
     * @param string $type 数据类型: regions, constellations, systems, stations, structures, items
     * @return array
     */
    private static function loadLocalData(string $type): array
    {
        $property = 'local' . ucfirst($type);
        
        if (self::$$property === null) {
            $file = base_path("data/eve_{$type}.json");
            if (file_exists($file)) {
                $content = file_get_contents($file);
                self::$$property = json_decode($content, true) ?? [];
            } else {
                self::$$property = [];
            }
        }
        
        return self::$$property;
    }
    
    /**
     * 获取所有星域数据
     */
    public static function getLocalRegions(): array
    {
        return self::loadLocalData('regions');
    }
    
    /**
     * 获取所有星座数据
     */
    public static function getLocalConstellations(): array
    {
        return self::loadLocalData('constellations');
    }
    
    /**
     * 获取所有星系数据
     */
    public static function getLocalSystems(): array
    {
        return self::loadLocalData('systems');
    }
    
    /**
     * 获取所有NPC空间站数据
     */
    public static function getLocalStations(): array
    {
        return self::loadLocalData('stations');
    }
    
    /**
     * 获取所有玩家建筑数据
     */
    public static function getLocalStructures(): array
    {
        return self::loadLocalData('structures');
    }
    
    /**
     * 获取所有物品数据
     */
    public static function getLocalItems(): array
    {
        return self::loadLocalData('items');
    }
    
    // ========================================================
    // 本地数据查找方法 (静态方法，查不到返回 null)
    // ========================================================
    
    /**
     * 本地查找物品名称
     * 
     * @param int $typeId 物品ID
     * @return string|null 物品名称，查不到返回null
     */
    public static function getLocalItemName(int $typeId): ?string
    {
        $items = self::getLocalItems();
        $key = (string) $typeId;
        
        if (isset($items[$key]) && isset($items[$key]['name'])) {
            return $items[$key]['name'];
        }
        
        return null;
    }
    
    /**
     * 本地查找星域名称
     * 
     * @param int $regionId 星域ID
     * @return string|null 星域名称，查不到返回null
     */
    public static function getLocalRegionName(int $regionId): ?string
    {
        $regions = self::getLocalRegions();
        $key = (string) $regionId;
        
        if (isset($regions[$key]) && isset($regions[$key]['name'])) {
            return $regions[$key]['name'];
        }
        
        return null;
    }
    
    /**
     * 本地查找星座信息
     * 
     * @param int $constellationId 星座ID
     * @return array|null {name, region_id}，查不到返回null
     */
    public static function getLocalConstellationInfo(int $constellationId): ?array
    {
        $constellations = self::getLocalConstellations();
        $key = (string) $constellationId;
        
        if (isset($constellations[$key])) {
            return $constellations[$key];
        }
        
        return null;
    }
    
    /**
     * 本地查找星系信息
     * 
     * @param int $systemId 星系ID
     * @return array|null {name, constellation_id, region_id, security}，查不到返回null
     */
    public static function getLocalSystemInfo(int $systemId): ?array
    {
        $systems = self::getLocalSystems();
        $key = (string) $systemId;
        
        if (isset($systems[$key])) {
            return $systems[$key];
        }
        
        return null;
    }
    
    /**
     * 本地查找NPC空间站信息
     * 
     * @param int $stationId 空间站ID
     * @return array|null {name, system_id, constellation_id, region_id}，查不到返回null
     */
    public static function getLocalStationInfo(int $stationId): ?array
    {
        $stations = self::getLocalStations();
        $key = (string) $stationId;
        
        if (isset($stations[$key])) {
            return $stations[$key];
        }
        
        return null;
    }
    
    /**
     * 本地查找玩家建筑信息
     * 
     * @param int $structureId 建筑ID
     * @return array|null {name, type, system_id, constellation_id, region_id}，查不到返回null
     */
    public static function getLocalStructureInfo(int $structureId): ?array
    {
        $structures = self::getLocalStructures();
        $key = (string) $structureId;
        
        if (isset($structures[$key])) {
            return $structures[$key];
        }
        
        return null;
    }
    
    /**
     * 通用名称查找（根据ID范围自动判断类型）
     * 
     * ID范围参考：
     * - 星域ID: 10000001 - 10000109
     * - 星座ID: 20000001 - 20001151
     * - 星系ID: 30000001 - 30008287
     * - NPC空间站ID: 60000004 - 60015600
     * - 玩家建筑ID: 1000000000000+（非常大的数字）
     * - 物品typeID: 其他
     * 
     * @param int $id ID
     * @return string|null 名称，查不到返回null
     */
    public static function getLocalName(int $id): ?string
    {
        // 星域 (10000001 - 10000999)
        if ($id >= 10000001 && $id < 11000000) {
            return self::getLocalRegionName($id);
        }
        
        // 星座 (20000001 - 20999999)
        if ($id >= 20000001 && $id < 21000000) {
            $info = self::getLocalConstellationInfo($id);
            return $info['name'] ?? null;
        }
        
        // 星系 (30000001 - 39999999)
        if ($id >= 30000001 && $id < 40000000) {
            $info = self::getLocalSystemInfo($id);
            return $info['name'] ?? null;
        }
        
        // NPC空间站 (60000000 - 69999999)
        if ($id >= 60000000 && $id < 70000000) {
            $info = self::getLocalStationInfo($id);
            return $info['name'] ?? null;
        }
        
        // 玩家建筑 (1000000000000+)
        if ($id >= 1000000000000) {
            $info = self::getLocalStructureInfo($id);
            return $info['name'] ?? null;
        }
        
        // 其他视为物品
        return self::getLocalItemName($id);
    }
    
    /**
     * 批量从本地数据获取名称
     * 
     * @param array $ids ID数组
     * @return array [id => name] 只返回找到的
     */
    public static function getLocalNames(array $ids): array
    {
        $names = [];
        
        foreach ($ids as $id) {
            $id = (int) $id;
            $name = self::getLocalName($id);
            if ($name !== null) {
                $names[$id] = $name;
            }
        }
        
        return $names;
    }
    
    /**
     * 获取物品数据库 (兼容旧版调用)
     * 优先使用新版本地数据
     */
    public function getItemDatabase()
    {
        if ($this->itemDatabase !== null) {
            return $this->itemDatabase;
        }
        
        // 从缓存获取（缓存 2 小时）
        $this->itemDatabase = Cache::remember('eve_names_database', 7200, function() {
            $database = [];
            
            // 1. 加载本地物品数据 (eve_items.json)
            $items = self::getLocalItems();
            foreach ($items as $id => $item) {
                if (isset($item['name'])) {
                    $database[(int) $id] = $item['name'];
                }
            }
            
            // 2. 加载本地星系数据 (eve_systems.json)
            $systems = self::getLocalSystems();
            foreach ($systems as $id => $system) {
                if (isset($system['name'])) {
                    $database[(int) $id] = $system['name'];
                }
            }
            
            // 3. 加载本地星域数据 (eve_regions.json)
            $regions = self::getLocalRegions();
            foreach ($regions as $id => $region) {
                if (isset($region['name'])) {
                    $database[(int) $id] = $region['name'];
                }
            }
            
            // 4. 加载本地星座数据 (eve_constellations.json)
            $constellations = self::getLocalConstellations();
            foreach ($constellations as $id => $constellation) {
                if (isset($constellation['name'])) {
                    $database[(int) $id] = $constellation['name'];
                }
            }
            
            // 5. 加载本地空间站数据 (eve_stations.json)
            $stations = self::getLocalStations();
            foreach ($stations as $id => $station) {
                if (isset($station['name'])) {
                    $database[(int) $id] = $station['name'];
                }
            }
            
            // 6. 加载本地建筑数据 (eve_structures.json)
            $structures = self::getLocalStructures();
            foreach ($structures as $id => $structure) {
                if (isset($structure['name'])) {
                    $database[(int) $id] = $structure['name'];
                }
            }
            
            // 7. 兜底：如果本地数据为空，尝试读取旧格式 eve_names.json
            if (empty($database) && file_exists($this->dataPath)) {
                $data = json_decode(file_get_contents($this->dataPath), true);
                if (is_array($data) && !empty($data)) {
                    foreach ($data as $id => $name) {
                        $database[(int) $id] = $name;
                    }
                }
            }
            
            // 8. 再兜底：尝试读取旧格式 items.json
            if (empty($database)) {
                $oldPath = base_path('data/items.json');
                if (file_exists($oldPath)) {
                    $data = json_decode(file_get_contents($oldPath), true);
                    foreach ($data as $item) {
                        if (isset($item['id']) && isset($item['name'])) {
                            $database[(int) $item['id']] = $item['name'];
                        }
                    }
                }
            }
            
            return $database;
        });
        
        return $this->itemDatabase;
    }
    
    /**
     * 获取站点→星系ID映射表
     * 优先使用本地空间站和建筑数据
     */
    public function getStationSystemMap()
    {
        if ($this->stationSystemMap !== null) {
            return $this->stationSystemMap;
        }
        
        $this->stationSystemMap = Cache::remember('eve_station_system_map', 7200, function() {
            $map = [];
            
            // 1. 从本地空间站数据构建映射
            $stations = self::getLocalStations();
            foreach ($stations as $stationId => $station) {
                if (isset($station['system_id'])) {
                    $map[(int) $stationId] = (int) $station['system_id'];
                }
            }
            
            // 2. 从本地建筑数据构建映射
            $structures = self::getLocalStructures();
            foreach ($structures as $structureId => $structure) {
                if (isset($structure['system_id'])) {
                    $map[(int) $structureId] = (int) $structure['system_id'];
                }
            }
            
            // 3. 兜底：如果本地数据为空，读取旧格式文件
            if (empty($map) && file_exists($this->stationSystemPath)) {
                $data = json_decode(file_get_contents($this->stationSystemPath), true);
                if (is_array($data)) {
                    foreach ($data as $stationId => $systemId) {
                        $map[(int) $stationId] = (int) $systemId;
                    }
                }
            }
            
            return $map;
        });
        
        return $this->stationSystemMap;
    }
    
    /**
     * 获取数据元信息
     */
    public function getDataMeta()
    {
        if (file_exists($this->metaPath)) {
            return json_decode(file_get_contents($this->metaPath), true);
        }
        return [
            'last_updated' => null,
            'source' => 'https://www.ceve-market.org/dumps/evedata.xlsx',
            'item_count' => 0,
        ];
    }
    
    /**
     * 检查是否需要更新数据（超过 7 天）
     */
    public function needsUpdate()
    {
        $meta = $this->getDataMeta();
        
        if (empty($meta['last_updated'])) {
            return true;
        }
        
        $lastUpdated = strtotime($meta['last_updated']);
        $now = time();
        $sevenDays = 7 * 24 * 60 * 60;
        
        return ($now - $lastUpdated) > $sevenDays;
    }
    
    /**
     * 调用 Python 脚本更新数据
     */
    public function updateData()
    {
        Log::info('开始更新 EVE 数据（调用 Python 脚本）...');
        
        try {
            $scriptPath = base_path('scripts/update_evedata.py');
            
            if (!file_exists($scriptPath)) {
                Log::error('Python 脚本不存在: ' . $scriptPath);
                return false;
            }
            
            // 确保 data 目录存在
            if (!is_dir(base_path('data'))) {
                mkdir(base_path('data'), 0775, true);
            }
            
            // 执行 Python 脚本
            $output = [];
            $returnCode = 0;
            exec('python3 ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $returnCode);
            
            $outputStr = implode("\n", $output);
            
            if ($returnCode !== 0) {
                Log::error('Python 脚本执行失败 (code=' . $returnCode . '): ' . $outputStr);
                return false;
            }
            
            Log::info('Python 脚本执行成功: ' . $outputStr);
            
            // 清除缓存，下次请求时会自动重新加载
            Cache::forget('eve_names_database');
            Cache::forget('eve_station_system_map');
            $this->itemDatabase = null;
            $this->stationSystemMap = null;
            
            // 验证新文件是否生成
            if (!file_exists($this->dataPath)) {
                Log::error('eve_names.json 未生成');
                return false;
            }
            
            $meta = $this->getDataMeta();
            $totalCount = $meta['total_entries'] ?? $meta['item_count'] ?? 0;
            Log::info('EVE 数据更新成功，共 ' . $totalCount . ' 条记录');
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('更新 EVE 数据异常：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 通过 ID 获取物品名称
     * 优先查本地静态数据，再查内存数据库，最后API兜底
     */
    public function getNameById($id, $type = 'item')
    {
        if (empty($id)) {
            return '未知';
        }
        
        $id = (int) $id;
        
        // 1. 优先查本地静态数据 (静态方法，不需要实例化)
        $localName = self::getLocalName($id);
        if ($localName !== null) {
            return $localName;
        }
        
        // 2. 从内存数据库查找 (兼容旧版)
        $database = $this->getItemDatabase();
        if (isset($database[$id])) {
            return $database[$id];
        }
        
        // 3. 本地找不到，调用官方 API
        Log::info('本地未找到 ID: ' . $id . '，调用官方 API');
        $name = $this->fetchNameFromApi($id, $type);
        
        if ($name && $name !== 'Unknown') {
            // 更新本地数据库缓存
            $this->addToDatabase($id, $name);
            return $name;
        }
        
        return '未知 ID: ' . $id;
    }
    
    /**
     * 从官方 API 获取名称
     */
    private function fetchNameFromApi($id, $type = 'item')
    {
        try {
            $baseUrl = rtrim(config('esi.base_url'), '/');
            $datasource = config('esi.datasource', 'serenity');
            
            // 使用批量查询接口（最多 1000 个 ID），加上 datasource 和 language 参数获取中文
            $response = Http::post($baseUrl . '/universe/names/?datasource=' . $datasource . '&language=zh', [
                $id
            ]);
            
            if ($response->ok()) {
                $result = $response->json();
                if (!empty($result) && isset($result[0]['name'])) {
                    return $result[0]['name'];
                }
            }
            
            // 如果批量接口失败，尝试单个查询
            if ($type === 'item' || $type === 'skill') {
                $response = Http::get($baseUrl . '/universe/types/' . $id . '/?datasource=' . $datasource . '&language=zh');
                if ($response->ok()) {
                    $data = $response->json();
                    return $data['name'] ?? null;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('从 API 获取名称失败：' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 批量获取名称
     * 优先查本地静态数据，再查内存数据库，最后API兜底
     */
    public function getNamesByIds($ids, $type = 'item')
    {
        $names = [];
        $missingIds = [];
        
        // 1. 先从本地静态数据查找
        foreach ($ids as $id) {
            $id = (int) $id;
            $localName = self::getLocalName($id);
            if ($localName !== null) {
                $names[$id] = $localName;
            }
        }
        
        // 2. 再从内存数据库查找
        $database = $this->getItemDatabase();
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset($names[$id])) {
                continue; // 已从本地找到
            }
            if (isset($database[$id])) {
                $names[$id] = $database[$id];
            } else {
                $missingIds[] = $id;
            }
        }
        
        // 3. 批量查询缺失的 ID (只查角色、军团、联盟等动态数据)
        if (!empty($missingIds)) {
            $apiNames = $this->fetchNamesFromApi($missingIds, $type);
            // 使用 + 运算符保留数字键（array_merge 会重新索引）
            $names = $names + $apiNames;
        }
        
        return $names;
    }
    
    /**
     * 批量从 API 获取名称
     */
    private function fetchNamesFromApi($ids, $type = 'item')
    {
        try {
            $baseUrl = rtrim(config('esi.base_url'), '/');
            $datasource = config('esi.datasource', 'serenity');
            
            // 使用批量查询接口，加上 datasource 和 language 参数获取中文
            $response = Http::post($baseUrl . '/universe/names/?datasource=' . $datasource . '&language=zh', $ids);
            
            if ($response->ok()) {
                $result = $response->json();
                $names = [];
                foreach ($result as $item) {
                    if (isset($item['id']) && isset($item['name'])) {
                        $names[$item['id']] = $item['name'];
                    }
                }
                return $names;
            }
            
            return [];
            
        } catch (\Exception $e) {
            Log::error('批量从 API 获取名称失败：' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 添加物品到数据库
     */
    private function addToDatabase($id, $name)
    {
        $database = $this->getItemDatabase();
        $database[$id] = $name;
        
        // 保存到缓存
        Cache::put('eve_names_database', $database, 7200);
        $this->itemDatabase = $database;
    }
    
    /**
     * 按名称模糊搜索 (用于自动补全)
     * @param string $query 搜索关键词
     * @param string $category 'ship' | 'system' | 'item'
     * @param int $limit 最大返回数
     * @return array [{id, name}]
     */
    public function searchByName(string $query, string $category = 'ship', int $limit = 15, array $validIds = []): array
    {
        $database = $this->getItemDatabase();
        if (empty($database) || strlen($query) < 1) {
            return [];
        }

        $queryLower = mb_strtolower($query, 'UTF-8');
        $results = [];
        $useWhitelist = ($category === 'ship' && !empty($validIds));

        foreach ($database as $id => $name) {
            // 按类别过滤
            if ($category === 'system') {
                if ($id < 30000000 || $id > 31999999) continue;
            } elseif ($category === 'ship') {
                if ($useWhitelist) {
                    if (!isset($validIds[$id])) continue;
                } else {
                    if ($id >= 30000000 || $id < 500) continue;
                }
            } elseif ($category === 'item') {
                // 市场物品搜索：排除星系等非物品 ID
                if ($id >= 30000000) continue;
            }

            // 名称模糊匹配
            $nameLower = mb_strtolower($name, 'UTF-8');
            if (mb_strpos($nameLower, $queryLower) === false) continue;

            $results[] = ['id' => (int) $id, 'name' => $name];
            if (count($results) >= $limit) break;
        }

        return $results;
    }

    /**
     * 获取数据更新时间
     */
    public function getLastUpdateTime()
    {
        $meta = $this->getDataMeta();
        return $meta['last_updated'] ?? '从未更新';
    }
    
    /**
     * 获取物品总数
     */
    public function getItemCount()
    {
        $meta = $this->getDataMeta();
        return $meta['item_count'] ?? 0;
    }
}
