<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 空间站名称翻译服务
 * 
 * ESI 的 universe/stations 接口不支持 language=zh 参数，返回的是英文站名。
 * 本服务通过逐段翻译的方式将英文站名转换为中文：
 * 1. 星系名：通过 universe/systems?language=zh 获取中文
 * 2. 军团名：通过 universe/names 获取中文
 * 3. 设施类型：静态映射表翻译
 */
class StationNameService
{
    private string $baseUrl;
    
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
     * @param int $stationId 空间站ID
     * @return string 中文名称，如果无法翻译则返回英文名称
     */
    public function getStationNameZh(int $stationId): string
    {
        // 检查缓存
        $cacheKey = "station_name_zh_{$stationId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            // 获取空间站信息
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
     * @param int $stationId 空间站ID
     * @return array|null ['name' => '中文名', 'system_id' => 星系ID, 'name_en' => '英文名']
     */
    public function getStationInfo(int $stationId): ?array
    {
        $cacheKey = "station_info_{$stationId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
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
     * @param int $structureId 建筑ID
     * @param string $accessToken 访问令牌
     * @return string 建筑名称
     */
    public function getStructureName(int $structureId, string $accessToken): string
    {
        $cacheKey = "structure_name_{$structureId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get("{$this->baseUrl}/universe/structures/{$structureId}/", [
                    'datasource' => 'serenity',
                    'language' => 'zh'
                ]);
            
            if ($response->ok()) {
                $name = $response->json()['name'] ?? "建筑 #{$structureId}";
                Cache::put($cacheKey, $name, 3600); // 缓存 1 小时
                return $name;
            }
            
            return "建筑 #{$structureId}";
            
        } catch (\Exception $e) {
            Log::warning("建筑名称获取失败: {$structureId} - " . $e->getMessage());
            return "建筑 #{$structureId}";
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
        
        // 先从缓存获取
        foreach ($stationIds as $id) {
            $cached = Cache::get("station_name_zh_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncached[] = $id;
            }
        }
        
        // 获取未缓存的
        foreach ($uncached as $id) {
            $names[$id] = $this->getStationNameZh($id);
        }
        
        return $names;
    }
}
