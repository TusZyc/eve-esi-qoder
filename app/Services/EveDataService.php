<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * EVE 数据服务 - 统一的 ID 转名称服务
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
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->dataPath = base_path('data/eve_names.json');
        $this->stationSystemPath = base_path('data/eve_station_systems.json');
        $this->metaPath = base_path('data/evedata_meta.json');
    }
    
    /**
     * 获取物品数据库
     */
    public function getItemDatabase()
    {
        if ($this->itemDatabase !== null) {
            return $this->itemDatabase;
        }
        
        // 从缓存获取（缓存 2 小时）
        $this->itemDatabase = Cache::remember('eve_names_database', 7200, function() {
            // 优先读取新版 eve_names.json（{id: name} 格式）
            if (file_exists($this->dataPath)) {
                $data = json_decode(file_get_contents($this->dataPath), true);
                if (is_array($data) && !empty($data)) {
                    $database = [];
                    foreach ($data as $id => $name) {
                        $database[(int) $id] = $name;
                    }
                    return $database;
                }
            }
            // 兜底：尝试读取旧格式 items.json
            $oldPath = base_path('data/items.json');
            if (file_exists($oldPath)) {
                $data = json_decode(file_get_contents($oldPath), true);
                $database = [];
                foreach ($data as $item) {
                    if (isset($item['id']) && isset($item['name'])) {
                        $database[(int) $item['id']] = $item['name'];
                    }
                }
                return $database;
            }
            return [];
        });
        
        return $this->itemDatabase;
    }
    
    /**
     * 获取站点→星系ID映射表
     */
    public function getStationSystemMap()
    {
        if ($this->stationSystemMap !== null) {
            return $this->stationSystemMap;
        }
        
        $this->stationSystemMap = Cache::remember('eve_station_system_map', 7200, function() {
            if (file_exists($this->stationSystemPath)) {
                $data = json_decode(file_get_contents($this->stationSystemPath), true);
                if (is_array($data)) {
                    $map = [];
                    foreach ($data as $stationId => $systemId) {
                        $map[(int) $stationId] = (int) $systemId;
                    }
                    return $map;
                }
            }
            return [];
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
     */
    public function getNameById($id, $type = 'item')
    {
        if (empty($id)) {
            return '未知';
        }
        
        $database = $this->getItemDatabase();
        
        // 优先从本地数据库查找
        if (isset($database[$id])) {
            return $database[$id];
        }
        
        // 本地找不到，调用官方 API
        Log::info('本地未找到物品 ID: ' . $id . '，调用官方 API');
        $name = $this->fetchNameFromApi($id, $type);
        
        if ($name && $name !== 'Unknown') {
            // 更新本地数据库
            $this->addToDatabase($id, $name);
            
            // 触发数据更新（可能数据过期了）- 暂时禁用，避免队列问题
            // if ($this->needsUpdate()) {
            //     Log::info('发现新物品，触发数据更新');
            //     dispatch(function() {
            //         $this->updateData();
            //     })->afterResponse();
            // }
            
            return $name;
        }
        
        return '未知物品 ID: ' . $id;
    }
    
    /**
     * 从官方 API 获取名称
     */
    private function fetchNameFromApi($id, $type = 'item')
    {
        try {
            // 使用批量查询接口（最多 1000 个 ID）
            $response = Http::post(config('esi.base_url') . 'universe/names/', [
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
                $response = Http::get(config('esi.base_url') . 'universe/types/' . $id . '/');
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
     */
    public function getNamesByIds($ids, $type = 'item')
    {
        $database = $this->getItemDatabase();
        $names = [];
        $missingIds = [];
        
        // 先从本地数据库查找
        foreach ($ids as $id) {
            if (isset($database[$id])) {
                $names[$id] = $database[$id];
            } else {
                $missingIds[] = $id;
            }
        }
        
        // 批量查询缺失的 ID
        if (!empty($missingIds)) {
            $apiNames = $this->fetchNamesFromApi($missingIds, $type);
            $names = array_merge($names, $apiNames);
        }
        
        return $names;
    }
    
    /**
     * 批量从 API 获取名称
     */
    private function fetchNamesFromApi($ids, $type = 'item')
    {
        try {
            // 使用批量查询接口
            $response = Http::post(config('esi.base_url') . 'universe/names/', $ids);
            
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
     * @param string $category 'ship' | 'system'
     * @param int $limit 最大返回数
     * @return array [{id, name}]
     */
    public function searchByName(string $query, string $category = 'ship', int $limit = 15): array
    {
        $database = $this->getItemDatabase();
        if (empty($database) || strlen($query) < 1) {
            return [];
        }

        $queryLower = mb_strtolower($query, 'UTF-8');
        $results = [];

        // 舰船名称排除关键词 - 排除蓝图、涂装、技能书、弹药、模块等非舰船物品
        $shipExcludePatterns = [
            '蓝图', '涂装', 'skin', '技能', '许可证',
            '弹药', '脚本', '晶体', '水晶', '燃料',
            '蓝图副本', '配方', '反应公式',
        ];

        foreach ($database as $id => $name) {
            // 按类别过滤 ID 范围
            if ($category === 'system') {
                // 星系 ID 范围: 30000000 - 31999999
                if ($id < 30000000 || $id > 31999999) continue;
            } elseif ($category === 'ship') {
                // 舰船 type_id 范围: 大致 580-50000，跳过明显非物品的 ID
                if ($id >= 30000000 || $id < 500) continue;
            }

            // 名称模糊匹配
            $nameLower = mb_strtolower($name, 'UTF-8');
            if (mb_strpos($nameLower, $queryLower) === false) continue;

            // 舰船类别额外过滤：排除非舰船物品
            if ($category === 'ship') {
                $excluded = false;
                foreach ($shipExcludePatterns as $pattern) {
                    if (mb_strpos($nameLower, mb_strtolower($pattern, 'UTF-8')) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) continue;
            }

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
