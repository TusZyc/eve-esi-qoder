<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;

/**
 * 空间站/建筑名称统一服务
 * 
 * 获取空间站和玩家建筑的中文名称，遵循以下优先级：
 * 
 * NPC空间站 (ID: 60000004 - 69999999):
 *   1. 本地数据 eve_stations.json（已翻译好的中文名）
 *   2. 缓存
 *   3. ESI API 获取英文名 → 逐段翻译成中文
 * 
 * 玩家建筑 (ID: 1000000000000+):
 *   1. 本地数据 eve_structures.json（公开建筑名）
 *   2. 缓存
 *   3. ESI API（需要token）→ 返回名称（ESI支持language=zh）
 *   4. 无法获取 → "未开放的玩家建筑"
 */
class StationNameService
{
    private string $baseUrl;
    
    /** 玩家建筑ID起始值 */
    private const STRUCTURE_ID_MIN = 1000000000000;
    
    /** 私有建筑的默认显示名称 */
    private const PRIVATE_STRUCTURE_NAME = '未开放的玩家建筑';
    
    /**
     * 设施类型中英文映射（长组合放前面，优先匹配）
     */
    private const FACILITY_MAP = [
        // 组合词优先匹配
        'Republic Parliament Bureau' => '共和议会安全局',
        'Parliament Bureau' => '议会安全局',
        'Navy Assembly Plant' => '海军组装工厂',
        'Navy Testing Facilities' => '海军测试设施',
        'Biotech Research Center' => '生物科技研究中心',
        'Research Center' => '研究中心',
        'Logistic Support' => '后勤支援',
        'Testing Facilities' => '测试设施',
        'Cloning Facility' => '克隆设施',
        'Mining Station' => '采矿站',
        'Food Packaging' => '食品包装厂',
        'Military School' => '军事学校',
        'Trading Post' => '贸易站',
        'Assembly Plant' => '组装工厂',
        // 单词
        'Fullerene Loom' => '富勒烯织机',
        'Fullerene' => '富勒烯',
        'Loom' => '织机',
        'Refinery' => '精炼厂',
        'Warehouse' => '仓库',
        'Storage' => '储藏设施',
        'Factory' => '工厂',
        'Hub' => '集散中心',
        'Academy' => '学院',
        'Foundry' => '铸造厂',
        'School' => '学校',
        'Treasury' => '金库',
        'Bureau' => '事务局',
        'Tribunal' => '法庭',
        'Accounting' => '会计处',
        'Mint' => '铸币厂',
        'Shipyard' => '船坞',
        'Station' => '空间站',
        'Headquarters' => '总部',
        'Law School' => '法学院',
        'Plantation' => '种植园',
        'Surveillance' => '监控站',
        'Commerce' => '商务站',
        'Customs Office' => '海关办公室',
        'Space Port' => '太空港',
    ];
    
    public function __construct()
    {
        $this->baseUrl = rtrim(config('esi.base_url'), '/');
    }
    
    /**
     * 获取空间站的中文名称
     * 
     * 优先级：本地数据 → 缓存 → ESI翻译
     * 
     * @param int $stationId 空间站ID
     * @return string 中文名称
     */
    public function getStationNameZh(int $stationId): string
    {
        // 1. 优先从本地数据获取（已翻译好的中文名）
        $localInfo = EveDataService::getLocalStationInfo($stationId);
        if ($localInfo && isset($localInfo['name'])) {
            return $localInfo['name'];
        }
        
        // 2. 检查缓存
        $cacheKey = "station_name_zh_{$stationId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 调用 ESI 获取并翻译
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/universe/stations/{$stationId}/", ['datasource' => 'serenity']);
            
            if (!$response->ok()) {
                return "空间站 #{$stationId}";
            }
            
            $data = $response->json();
            $name = $data['name'] ?? "空间站 #{$stationId}";
            $systemId = $data['system_id'] ?? 0;
            $ownerId = $data['owner'] ?? 0;
            
            // 翻译站名
            $translatedName = $this->translateStationName($name, $systemId, $ownerId);
            
            // 缓存 24 小时
            Cache::put($cacheKey, $translatedName, 86400);
            
            return $translatedName;
            
        } catch (\Exception $e) {
            Log::warning("空间站名称获取失败: {$stationId} - " . $e->getMessage());
            return "空间站 #{$stationId}";
        }
    }
    
    /**
     * 获取空间站信息（包含名称和星系ID）
     * 
     * 优先级：本地数据 → 缓存 → ESI翻译
     * 
     * @param int $stationId 空间站ID
     * @return array|null ['name' => '中文名', 'system_id' => 星系ID, 'name_en' => '英文名']
     */
    public function getStationInfo(int $stationId): ?array
    {
        // 1. 优先从本地数据获取
        $localInfo = EveDataService::getLocalStationInfo($stationId);
        if ($localInfo && isset($localInfo['name'])) {
            return [
                'name' => $localInfo['name'],
                'name_en' => null, // 本地数据没有英文名
                'system_id' => $localInfo['system_id'] ?? 0,
                'owner' => 0, // 本地数据没有军团ID
            ];
        }
        
        // 2. 检查缓存
        $cacheKey = "station_info_{$stationId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 调用 ESI 获取并翻译
        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/universe/stations/{$stationId}/", ['datasource' => 'serenity']);
            
            if (!$response->ok()) {
                return null;
            }
            
            $data = $response->json();
            $nameEn = $data['name'] ?? '';
            $systemId = $data['system_id'] ?? 0;
            $ownerId = $data['owner'] ?? 0;
            
            // 翻译站名
            $nameZh = $this->translateStationName($nameEn, $systemId, $ownerId);
            
            $info = [
                'name' => $nameZh,
                'name_en' => $nameEn,
                'system_id' => $systemId,
                'owner' => $ownerId,
            ];
            
            Cache::put($cacheKey, $info, 86400);
            
            return $info;
            
        } catch (\Exception $e) {
            Log::warning("空间站信息获取失败: {$stationId} - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 翻译空间站名称
     * 
     * @param string $name 英文站名
     * @param int $systemId 星系ID
     * @param int $ownerId 所有者军团ID
     * @return string 中文站名
     */
    public function translateStationName(string $name, int $systemId, int $ownerId): string
    {
        // 1) 替换星系名
        if ($systemId > 0) {
            [$zhSys, $enSys] = $this->getSystemNames($systemId);
            
            if ($enSys && $zhSys && str_starts_with($name, $enSys)) {
                $name = $zhSys . substr($name, strlen($enSys));
            }
        }
        
        // 2) Moon -> 卫星
        $name = preg_replace('/\bMoon\b/', '卫星', $name);
        
        // 3) Planet -> 行星
        $name = preg_replace('/\bPlanet\b/', '行星', $name);
        
        // 4) 替换军团名
        if ($ownerId > 0) {
            [$zhCorp, $enCorp] = $this->getCorporationNames($ownerId);
            
            if ($enCorp && $zhCorp && $enCorp !== $zhCorp) {
                $name = str_replace($enCorp, $zhCorp, $name);
            }
        }
        
        // 5) 翻译设施类型（遍历所有映射，替换所有匹配的词）
        foreach (self::FACILITY_MAP as $en => $zh) {
            if (str_contains($name, $en)) {
                $name = str_replace($en, $zh, $name);
            }
        }
        
        return $name;
    }
    
    /**
     * 获取星系的中英文名称
     * 
     * @param int $systemId 星系ID
     * @return array [中文名, 英文名]
     */
    private function getSystemNames(int $systemId): array
    {
        $zhCacheKey = "eve_sysname_{$systemId}";
        $enCacheKey = "eve_sysname_en_{$systemId}";
        
        $zhSys = Cache::get($zhCacheKey);
        $enSys = Cache::get($enCacheKey);
        
        if ($zhSys === null || $enSys === null) {
            try {
                // 并行获取中英文名称
                $responses = Http::pool(function ($pool) use ($systemId) {
                    $pool->as('zh')->timeout(5)
                        ->get("{$this->baseUrl}/universe/systems/{$systemId}/", [
                            'datasource' => 'serenity',
                            'language' => 'zh'
                        ]);
                    $pool->as('en')->timeout(5)
                        ->get("{$this->baseUrl}/universe/systems/{$systemId}/", [
                            'datasource' => 'serenity',
                            'language' => 'en'
                        ]);
                });
                
                if (isset($responses['zh']) && $responses['zh']->ok()) {
                    $zhSys = $responses['zh']->json()['name'] ?? '';
                    Cache::put($zhCacheKey, $zhSys, 86400);
                }
                
                if (isset($responses['en']) && $responses['en']->ok()) {
                    $enSys = $responses['en']->json()['name'] ?? '';
                    Cache::put($enCacheKey, $enSys, 86400);
                }
                
            } catch (\Exception $e) {
                Log::warning("星系名称获取失败: {$systemId} - " . $e->getMessage());
            }
        }
        
        return [$zhSys ?? '', $enSys ?? ''];
    }
    
    /**
     * 获取军团的中英文名称
     * 
     * @param int $corpId 军团ID
     * @return array [中文名, 英文名]
     */
    private function getCorporationNames(int $corpId): array
    {
        $zhCacheKey = "eve_name_{$corpId}";
        $enCacheKey = "eve_corpname_en_{$corpId}";
        
        $zhCorp = Cache::get($zhCacheKey);
        $enCorp = Cache::get($enCacheKey);
        
        // 中文名通过 universe/names 获取
        if ($zhCorp === null) {
            try {
                $resp = Http::timeout(5)
                    ->post("{$this->baseUrl}/universe/names/", [$corpId]);
                
                if ($resp->ok()) {
                    $items = $resp->json();
                    if (!empty($items)) {
                        $zhCorp = $items[0]['name'] ?? '';
                        Cache::put($zhCacheKey, $zhCorp, 86400);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("军团中文名获取失败: {$corpId} - " . $e->getMessage());
            }
        }
        
        // 英文名通过 corporations 接口获取
        if ($enCorp === null) {
            try {
                $resp = Http::timeout(5)
                    ->get("{$this->baseUrl}/corporations/{$corpId}/", ['datasource' => 'serenity']);
                
                if ($resp->ok()) {
                    $enCorp = $resp->json()['name'] ?? '';
                    Cache::put($enCacheKey, $enCorp, 86400);
                }
            } catch (\Exception $e) {
                Log::warning("军团英文名获取失败: {$corpId} - " . $e->getMessage());
            }
        }
        
        return [$zhCorp ?? '', $enCorp ?? ''];
    }
    
    /**
     * 获取玩家建筑名称
     * 
     * 优先级：本地数据 → 缓存 → ESI API → "未开放的玩家建筑"
     * 
     * @param int $structureId 建筑ID
     * @param string|null $accessToken 访问令牌（可选，私有建筑需要）
     * @return string 建筑名称
     */
    public function getStructureName(int $structureId, ?string $accessToken = null): string
    {
        // 1. 优先从本地数据获取（公开建筑）
        $localInfo = EveDataService::getLocalStructureInfo($structureId);
        if ($localInfo && isset($localInfo['name'])) {
            return $localInfo['name'];
        }
        
        // 2. 检查缓存
        $cacheKey = "structure_name_{$structureId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 3. 如果没有 token，直接返回私有建筑默认名称
        if (!$accessToken) {
            Cache::put($cacheKey, self::PRIVATE_STRUCTURE_NAME, 86400);
            return self::PRIVATE_STRUCTURE_NAME;
        }
        
        // 4. 调用 ESI API 获取建筑名称
        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get("{$this->baseUrl}/universe/structures/{$structureId}/", [
                    'datasource' => 'serenity',
                    'language' => 'zh'
                ]);
            
            if ($response->ok()) {
                $name = $response->json()['name'] ?? self::PRIVATE_STRUCTURE_NAME;
                Cache::put($cacheKey, $name, 3600); // 缓存 1 小时
                return $name;
            }
            
            // 403/404 等错误表示私有建筑或不存在
            if ($response->status() === 403 || $response->status() === 404) {
                Cache::put($cacheKey, self::PRIVATE_STRUCTURE_NAME, 86400);
                return self::PRIVATE_STRUCTURE_NAME;
            }
            
            return self::PRIVATE_STRUCTURE_NAME;
            
        } catch (\Exception $e) {
            Log::warning("建筑名称获取失败: {$structureId} - " . $e->getMessage());
            return self::PRIVATE_STRUCTURE_NAME;
        }
    }
    
    /**
     * 批量获取空间站中文名称
     * 
     * @param array $stationIds 空间站ID数组
     * @return array [id => 中文名]
     */
    public function getStationNamesZh(array $stationIds): array
    {
        $names = [];
        $uncached = [];
        
        foreach ($stationIds as $id) {
            // 1. 优先从本地数据获取
            $localInfo = EveDataService::getLocalStationInfo($id);
            if ($localInfo && isset($localInfo['name'])) {
                $names[$id] = $localInfo['name'];
                continue;
            }
            
            // 2. 从缓存获取
            $cached = Cache::get("station_name_zh_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncached[] = $id;
            }
        }
        
        // 3. 获取未缓存的
        foreach ($uncached as $id) {
            $names[$id] = $this->getStationNameZh($id);
        }
        
        return $names;
    }
    
    /**
     * 统一的名称获取方法（根据ID自动判断类型）
     * 
     * ID范围：
     * - NPC空间站: 60000004 - 69999999
     * - 玩家建筑: 1000000000000+
     * 
     * @param int $locationId 位置ID（空间站或建筑）
     * @param string|null $accessToken 访问令牌（获取私有建筑需要）
     * @return string 名称
     */
    public function getName(int $locationId, ?string $accessToken = null): string
    {
        // 玩家建筑 (ID >= 1000000000000)
        if ($locationId >= self::STRUCTURE_ID_MIN) {
            return $this->getStructureName($locationId, $accessToken);
        }
        
        // NPC空间站
        return $this->getStationNameZh($locationId);
    }
    
    /**
     * 批量获取位置名称（自动判断类型）
     * 
     * @param array $locationIds 位置ID数组
     * @param string|null $accessToken 访问令牌（获取私有建筑需要）
     * @return array [id => 名称]
     */
    public function getNames(array $locationIds, ?string $accessToken = null): array
    {
        $names = [];
        
        // 分类处理
        $stationIds = [];
        $structureIds = [];
        
        foreach ($locationIds as $id) {
            if ($id >= self::STRUCTURE_ID_MIN) {
                $structureIds[] = $id;
            } else {
                $stationIds[] = $id;
            }
        }
        
        // 批量获取空间站名称
        if (!empty($stationIds)) {
            $stationNames = $this->getStationNamesZh($stationIds);
            $names = array_merge($names, $stationNames);
        }
        
        // 获取建筑名称
        foreach ($structureIds as $id) {
            $names[$id] = $this->getStructureName($id, $accessToken);
        }
        
        return $names;
    }
}
