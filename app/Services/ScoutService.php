<?php

namespace App\Services;

use App\Models\ScoutResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 斥候工具服务 - 扫描器结果解析
 */
class ScoutService
{
    private EveDataService $eveDataService;
    private string $esiBaseUrl;
    private string $datasource;
    
    /**
     * 本地物品分类数据缓存
     */
    private static ?array $eveItemsData = null;
    
    /**
     * 保存时间选项（小时）
     */
    public const RETENTION_OPTIONS = [
        2 => '2小时',
        12 => '12小时',
        48 => '2天',
        168 => '7天',
        0 => '永久',
    ];
    
    /**
     * 默认保存时间
     */
    public const DEFAULT_RETENTION = 2;
    
    /**
     * 舰船分类 ID 映射（group_id => 中文名）
     */
    private const SHIP_GROUPS = [
        25 => '轻型护卫舰',
        26 => '巡洋舰',
        27 => '战列舰',
        28 => '工业舰',
        29 => '太空舱',
        30 => '泰坦',
        31 => '穿梭机',
        237 => '货舰',
        324 => '突击护卫舰',
        358 => '重型突击巡洋舰',
        380 => '深空运输舰',
        381 => '封锁运输舰',
        419 => '战列巡洋舰',
        420 => '驱逐舰',
        463 => '采矿驳船',
        513 => '超级航母',
        540 => '指挥舰',
        541 => '拦截舰',
        543 => '采掘者',
        547 => '航空母舰',
        659 => '后勤巡洋舰',
        830 => '隐形轰炸舰',
        831 => '电子攻击护卫舰',
        832 => '后勤护卫舰',
        833 => '武装工业舰',
        834 => '远征护卫舰',
        893 => '侦察舰',
        894 => '重型拦截巡洋舰',
        898 => '黑隐特勤舰',
        900 => '无畏舰',
        906 => '战斗侦察舰',
        941 => '工业指挥舰',
        963 => '战略巡洋舰',
        1022 => '旗舰工业舰',
        1201 => '突击驱逐舰',
        1202 => '指挥驱逐舰',
        1283 => '战术驱逐舰',
        1534 => '指挥突击舰',
        1538 => '先驱者战列舰',
        1972 => '旗舰级战列舰',
        2001 => '护卫舰',
        2021 => '驱逐舰',
    ];
    
    /**
     * 建筑分类 ID 映射
     */
    private const STRUCTURE_GROUPS = [
        364 => '空间站',
        365 => '海关办公室',
        438 => '控制塔',
        883 => '工程复合体',
        1049 => '精炼厂',
        1404 => '空堡',
        1406 => '阿斯特罗科技堡垒',
        1408 => '福提萨',
        1410 => '吉塔极速堡垒',
    ];
    
    /**
     * 货柜分类
     */
    private const CONTAINER_GROUP = 319;
    
    /**
     * 天体类型 ID 范围
     */
    private const CELESTIAL_ID_START = 1;
    private const CELESTIAL_ID_END = 50;
    
    public function __construct(EveDataService $eveDataService)
    {
        $this->eveDataService = $eveDataService;
        $this->esiBaseUrl = config('esi.base_url');
        $this->datasource = config('esi.datasource', 'serenity');
    }
    
    /**
     * 加载本地物品分类数据（eve_items.json）
     * 格式: { "type_id": { "name": "物品名", "category": ["舰船", "旗舰", "战略货舰", "种族"] } }
     */
    private function loadEveItemsData(): array
    {
        if (self::$eveItemsData !== null) {
            return self::$eveItemsData;
        }
        
        $filePath = base_path('data/eve_items.json');
        
        if (!file_exists($filePath)) {
            Log::warning('斥候工具 - eve_items.json 不存在');
            self::$eveItemsData = [];
            return [];
        }
        
        $content = file_get_contents($filePath);
        self::$eveItemsData = json_decode($content, true) ?: [];
        
        return self::$eveItemsData;
    }
    
    /**
     * 从 eve_items.json 查找物品并获取分类信息
     * 
     * @return array|null ['type_id' => int, 'name' => string, 'category' => array]
     */
    private function findItemWithCategory(string $name): ?array
    {
        $items = $this->loadEveItemsData();
        
        // 第一轮：精确匹配，优先选择有分类的物品（排除皮肤/涂装）
        foreach ($items as $typeId => $item) {
            $itemName = $item['name'] ?? '';
            $category = $item['category'] ?? [];
            
            if ($itemName === $name && !empty($category)) {
                return [
                    'type_id' => (int) $typeId,
                    'name' => $itemName,
                    'category' => $category,
                ];
            }
        }
        
        // 第二轮：精确匹配（允许空分类）
        foreach ($items as $typeId => $item) {
            $itemName = $item['name'] ?? '';
            
            if ($itemName === $name) {
                return [
                    'type_id' => (int) $typeId,
                    'name' => $itemName,
                    'category' => $item['category'] ?? [],
                ];
            }
        }
        
        // 第三轮：模糊匹配，只匹配有分类的物品
        foreach ($items as $typeId => $item) {
            $itemName = $item['name'] ?? '';
            $category = $item['category'] ?? [];
            
            if (empty($category)) continue;
            
            // 名称包含关系
            if (mb_strpos($itemName, $name) !== false || mb_strpos($name, $itemName) !== false) {
                return [
                    'type_id' => (int) $typeId,
                    'name' => $itemName,
                    'category' => $category,
                ];
            }
        }
        
        return null;
    }
    
    /**
     * 解析剪贴板文本
     * 
     * 游戏扫描器格式（TAB分隔）：
     * 数字[TAB]名称[TAB]类型[其他列...]
     * 例如: "16242\t金龙级 - Rordambrad Taron\t金龙级\t..."
     */
    public function parseClipboardText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $itemCounts = []; // 用于聚合相同名称的物品
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 按 TAB 分割
            $parts = preg_split('/\t/', $line);
            
            if (count($parts) >= 2) {
                // 第二列是物品名称（可能包含 "中文名 - 英文名" 格式）
                $name = trim($parts[1]);
                
                // 提取中文名称（去掉 " - 英文名" 部分）
                if (preg_match('/^(.+?)\s*-\s*.+$/u', $name, $m)) {
                    $chineseName = trim($m[1]);
                } else {
                    $chineseName = $name;
                }
                
                // 如果第三列存在，可能是更准确的类型名称
                if (count($parts) >= 3) {
                    $typeName = trim($parts[2]);
                    if (!empty($typeName) && $typeName !== $chineseName) {
                        // 使用第三列作为类型名称（更准确）
                        $chineseName = $typeName;
                    }
                }
                
                // 聚合相同名称的物品
                if (!isset($itemCounts[$chineseName])) {
                    $itemCounts[$chineseName] = [
                        'raw_name' => $name,
                        'chinese_name' => $chineseName,
                        'english_name' => null,
                        'quantity' => 0,
                    ];
                }
                $itemCounts[$chineseName]['quantity']++;
            }
        }
        
        return array_values($itemCounts);
    }
    
    /**
     * 解析物品并获取 type_id 和分类信息
     */
    public function resolveItems(array $items): array
    {
        $resolved = [];
        
        foreach ($items as $item) {
            $chineseName = $item['chinese_name'];
            
            // 1. 优先从 eve_items.json 查找（包含分类信息）
            $itemInfo = $this->findItemWithCategory($chineseName);
            
            if ($itemInfo !== null) {
                $category = $itemInfo['category'];
                $mainCategory = $category[0] ?? '其他';
                $subCategory = $category[1] ?? null;  // 如: 旗舰
                $specificType = $category[2] ?? null; // 如: 战略货舰
                
                $resolved[] = [
                    ...$item,
                    'type_id' => $itemInfo['type_id'],
                    'resolved' => true,
                    'group_id' => null,
                    'group_name' => $specificType,
                    'category' => $mainCategory,
                    'category_array' => $category,
                ];
            } else {
                // 2. ESI 兜底
                $esiInfo = $this->findTypeIdFromEsiWithGroup($chineseName);
                
                if ($esiInfo !== null) {
                    $resolved[] = [
                        ...$item,
                        'type_id' => $esiInfo['type_id'],
                        'resolved' => true,
                        'group_id' => $esiInfo['group_id'],
                        'group_name' => $esiInfo['group_name'],
                        'category' => $esiInfo['category'],
                        'category_array' => [],
                    ];
                } else {
                    // 完全找不到
                    $resolved[] = [
                        ...$item,
                        'type_id' => null,
                        'resolved' => false,
                        'group_id' => null,
                        'group_name' => null,
                        'category' => '其他',
                        'category_array' => [],
                    ];
                }
            }
        }
        
        return $resolved;
    }
    
    /**
     * 从本地数据查找 type_id
     */
    private function findTypeIdLocal(string $name): ?int
    {
        // 先尝试精确匹配
        $items = $this->eveDataService->getLocalItems();
        
        foreach ($items as $item) {
            if (isset($item['id']) && isset($item['name'])) {
                if ($item['name'] === $name) {
                    return (int) $item['id'];
                }
            }
        }
        
        // 再尝试模糊匹配
        $results = $this->eveDataService->searchByName($name, 'item', 1);
        
        if (!empty($results)) {
            return $results[0]['id'];
        }
        
        return null;
    }
    
    /**
     * 从 ESI API 查找 type_id
     */
    private function findTypeIdFromEsi(string $name): ?int
    {
        try {
            $response = Http::timeout(10)->get($this->esiBaseUrl . 'search/', [
                'search' => $name,
                'categories' => 'inventory_type',
                'datasource' => $this->datasource,
                'strict' => 'false',
            ]);
            
            if (!$response->ok()) {
                return null;
            }
            
            $data = $response->json();
            $typeIds = $data['inventory_type'] ?? [];
            
            if (!empty($typeIds)) {
                // 缓存找到的映射
                $typeId = $typeIds[0];
                $this->cacheTypeNameMapping($name, $typeId);
                return $typeId;
            }
            
        } catch (\Exception $e) {
            Log::debug("ESI 搜索失败 ({$name}): " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 从 ESI API 查找 type_id 并获取分组信息（兜底方法）
     */
    private function findTypeIdFromEsiWithGroup(string $name): ?array
    {
        try {
            $response = Http::timeout(10)->get($this->esiBaseUrl . 'search/', [
                'search' => $name,
                'categories' => 'inventory_type',
                'datasource' => $this->datasource,
                'strict' => 'false',
            ]);
            
            if (!$response->ok()) {
                return null;
            }
            
            $data = $response->json();
            $typeIds = $data['inventory_type'] ?? [];
            
            if (!empty($typeIds)) {
                $typeId = $typeIds[0];
                
                // 获取类型详情
                $typeResponse = Http::timeout(10)->get($this->esiBaseUrl . "universe/types/{$typeId}/", [
                    'datasource' => $this->datasource,
                ]);
                
                if ($typeResponse->ok()) {
                    $typeData = $typeResponse->json();
                    $groupId = $typeData['group_id'] ?? null;
                    
                    // 根据 group_id 判断分类
                    $category = $this->guessCategoryFromGroupId($groupId);
                    
                    // 获取分组名称
                    $groupName = $this->getGroupName($groupId);
                    
                    return [
                        'type_id' => $typeId,
                        'group_id' => $groupId,
                        'group_name' => $groupName,
                        'category' => $category,
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Log::debug("ESI 搜索失败 ({$name}): " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 根据 group_id 猜测分类
     */
    private function guessCategoryFromGroupId(?int $groupId): string
    {
        if ($groupId === null) {
            return '其他';
        }
        
        // 舰船分组 ID 范围（补充了 902 战略货舰）
        $shipGroupIds = [
            25, 26, 27, 28, 29, 30, 31, 237, 324, 358, 380, 381, 419, 420, 463,
            513, 540, 541, 543, 547, 659, 830, 831, 832, 833, 834, 893, 894,
            898, 900, 902, 906, 941, 963, 1022, 1201, 1202, 1283, 1305, 1534, 1538,
            1972, 2001, 2021,
        ];
        
        if (in_array($groupId, $shipGroupIds)) {
            return '舰船';
        }
        
        return '其他';
    }
    
    /**
     * 获取分组名称
     */
    private function getGroupName(?int $groupId): ?string
    {
        if ($groupId === null) {
            return null;
        }
        
        return Cache::remember("scout_group_{$groupId}_zh", 86400, function () use ($groupId) {
            try {
                $response = Http::timeout(10)->get($this->esiBaseUrl . "universe/groups/{$groupId}/", [
                    'datasource' => $this->datasource,
                    'language' => 'zh',
                ]);
                
                if ($response->ok()) {
                    return $response->json('name');
                }
            } catch (\Exception $e) {
                Log::debug("获取 group name 失败 ({$groupId}): " . $e->getMessage());
            }
            
            return null;
        });
    }
    
    /**
     * 缓存名称到 type_id 的映射
     */
    private function cacheTypeNameMapping(string $name, int $typeId): void
    {
        $cacheKey = "scout_type_name_{$name}";
        Cache::put($cacheKey, $typeId, 86400 * 7); // 缓存7天
    }
    
    /**
     * 获取物品的分类信息
     */
    public function getItemCategories(array $resolvedItems): array
    {
        $typeIds = array_filter(array_column($resolvedItems, 'type_id'));
        
        if (empty($typeIds)) {
            return [];
        }
        
        // 批量获取 group_id
        $groupMap = $this->batchGetGroupIds($typeIds);
        
        // 丰富物品数据
        foreach ($resolvedItems as &$item) {
            $typeId = $item['type_id'];
            
            // 如果已经有正确的分类（从本地数据获取），则保留
            $existingCategory = $item['category'] ?? '其他';
            $hasValidCategory = !empty($existingCategory) && $existingCategory !== '其他';
            
            if ($typeId && isset($groupMap[$typeId])) {
                $item['group_id'] = $groupMap[$typeId]['group_id'];
                // 只有当没有有效分类时，才覆盖 group_name
                if (!$hasValidCategory) {
                    $item['group_name'] = $groupMap[$typeId]['group_name'];
                    $item['category'] = $this->determineCategory($typeId, $groupMap[$typeId]['group_id'] ?? null);
                }
            } else {
                $item['group_id'] = null;
                if (!$hasValidCategory) {
                    $item['group_name'] = null;
                    $item['category'] = $this->determineCategory($typeId, null);
                }
            }
        }
        
        return $resolvedItems;
    }
    
    /**
     * 批量获取 type_id 对应的 group_id
     */
    private function batchGetGroupIds(array $typeIds): array
    {
        $result = [];
        $uncachedIds = [];
        
        // 先检查缓存
        foreach ($typeIds as $typeId) {
            $cacheKey = "scout_type_group_{$typeId}";
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $result[$typeId] = $cached;
            } else {
                $uncachedIds[] = $typeId;
            }
        }
        
        // 对未缓存的 ID，从 ESI 获取
        foreach ($uncachedIds as $typeId) {
            $groupInfo = $this->getTypeGroupFromEsi($typeId);
            $result[$typeId] = $groupInfo;
            Cache::put("scout_type_group_{$typeId}", $groupInfo, 86400);
        }
        
        return $result;
    }
    
    /**
     * 从 ESI 获取物品的 group_id
     */
    private function getTypeGroupFromEsi(int $typeId): array
    {
        try {
            $response = Http::timeout(10)->get($this->esiBaseUrl . "universe/types/{$typeId}/", [
                'datasource' => $this->datasource,
            ]);
            
            if ($response->ok()) {
                $data = $response->json();
                $groupId = $data['group_id'] ?? null;
                $groupName = $this->getGroupName($groupId);
                
                return [
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                ];
            }
        } catch (\Exception $e) {
            Log::debug("获取 type group 失败 ({$typeId}): " . $e->getMessage());
        }
        
        return ['group_id' => null, 'group_name' => null];
    }
    
    /**
     * 确定物品的大类
     */
    private function determineCategory(?int $typeId, ?int $groupId): string
    {
        if ($typeId === null) {
            return '其他';
        }
        
        // 太空舱
        if ($groupId === 29) {
            return '太空舱';
        }
        
        // 舰船（所有舰船分组）
        if (isset(self::SHIP_GROUPS[$groupId]) && $groupId !== 29) {
            return '舰船';
        }
        
        // 建筑
        if (isset(self::STRUCTURE_GROUPS[$groupId])) {
            return '建筑';
        }
        
        // 货柜
        if ($groupId === self::CONTAINER_GROUP) {
            return '货柜';
        }
        
        // 残骸（通常在特定 ID 范围或名称包含"残骸"）
        // 无人机分组
        if (in_array($groupId, [100, 101, 102, 543, 941])) {
            return '无人机';
        }
        
        // 天体（恒星、行星、卫星、小行星带）
        if ($typeId >= self::CELESTIAL_ID_START && $typeId <= self::CELESTIAL_ID_END) {
            return '天体';
        }
        
        return '其他';
    }
    
    /**
     * 生成统计数据
     */
    public function generateStatistics(array $items): array
    {
        $totalObjects = 0;
        $categories = [];
        
        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 0;
            $totalObjects += $quantity;
            
            $category = $item['category'] ?? '其他';
            $groupName = $item['group_name'] ?? '其他';
            
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'count' => 0,
                    'subcategories' => [],
                ];
            }
            
            $categories[$category]['count'] += $quantity;
            
            // 对于舰船，按具体舰种分组
            if ($category === '舰船' && $groupName) {
                if (!isset($categories[$category]['subcategories'][$groupName])) {
                    $categories[$category]['subcategories'][$groupName] = [
                        'count' => 0,
                        'items' => [],
                    ];
                }
                $categories[$category]['subcategories'][$groupName]['count'] += $quantity;
                $categories[$category]['subcategories'][$groupName]['items'][] = [
                    'name' => $item['chinese_name'],
                    'quantity' => $quantity,
                ];
            } else {
                // 其他分类，直接放 items
                if (!isset($categories[$category]['items'])) {
                    $categories[$category]['items'] = [];
                }
                $categories[$category]['items'][] = [
                    'name' => $item['chinese_name'],
                    'quantity' => $quantity,
                ];
            }
        }
        
        return [
            'total_objects' => $totalObjects,
            'categories' => $categories,
        ];
    }
    
    /**
     * 生成唯一 ID
     */
    public function generateUniqueId(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $maxAttempts = 10;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = '';
            for ($i = 0; $i < 6; $i++) {
                $id .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // 检查唯一性
            if (!ScoutResult::find($id)) {
                return $id;
            }
        }
        
        // 如果多次冲突，使用更长的 ID
        return Str::random(8);
    }
    
    /**
     * 保存扫描结果
     */
    public function saveResult(array $items, array $statistics, int $retentionHours, string $ipHash): ScoutResult
    {
        $id = $this->generateUniqueId();
        
        $expiresAt = null;
        if ($retentionHours > 0) {
            $expiresAt = now()->addHours($retentionHours);
        }
        
        $result = ScoutResult::create([
            'id' => $id,
            'items' => $items,
            'statistics' => $statistics,
            'ip_hash' => $ipHash,
            'retention_hours' => $retentionHours,
            'created_at' => now(),
            'expires_at' => $expiresAt,
        ]);
        
        return $result;
    }
    
    /**
     * 完整处理流程
     */
    public function process(string $clipboardText, int $retentionHours, string $ipHash): ScoutResult
    {
        // 1. 解析剪贴板
        $items = $this->parseClipboardText($clipboardText);
        
        Log::info('斥候工具 - 解析结果', ['item_count' => count($items)]);
        
        // 2. 解析物品 ID
        $resolved = $this->resolveItems($items);
        
        // 3. 获取分类信息
        $categorized = $this->getItemCategories($resolved);
        
        // 4. 生成统计
        $statistics = $this->generateStatistics($categorized);
        
        // 5. 保存结果
        return $this->saveResult($categorized, $statistics, $retentionHours, $ipHash);
    }
    
    /**
     * 获取保存时间选项
     */
    public static function getRetentionOptions(): array
    {
        return self::RETENTION_OPTIONS;
    }
    
    /**
     * 生成 IP 哈希
     */
    public static function generateIpHash(string $ip): string
    {
        return hash('sha256', $ip . config('app.key'));
    }
}