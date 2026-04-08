<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 装备分类服务 - 基于 eve_items.json 的多级分类结构
 * 
 * 分类层级：
 * - 一级：舰船装备
 * - 二级：工程装备、护盾、推进器、炮台和发射器、电子战、船体和装甲等（13个）
 * - 三级：具体装备类型（如电容回充器、护盾扩展装置等）
 * - 四级：尺寸规格（小型/中型/大型/旗舰等，部分有）
 */
class ItemCategoryService
{
    /**
     * 物品数据缓存
     */
    private static $itemsData = null;
    
    /**
     * 装备分类树缓存
     */
    private static $categoryTree = null;
    
    /**
     * 装备分类树（带物品列表）缓存
     */
    private static $categoryTreeWithItems = null;
    
    /**
     * 加载物品数据
     */
    private static function loadItemsData(): array
    {
        if (self::$itemsData === null) {
            $file = base_path('data/eve_items.json');
            if (file_exists($file)) {
                $content = file_get_contents($file);
                self::$itemsData = json_decode($content, true) ?? [];
            } else {
                self::$itemsData = [];
            }
        }
        return self::$itemsData;
    }
    
    /**
     * 获取舰船装备的分类树（不含物品列表）
     * 
     * @return array [
     *   '工程装备' => [
     *     '电容回充器' => [],
     *     '电容电池' => ['小型', '中型', '大型', '微型', '旗舰'],
     *     ...
     *   ],
     *   ...
     * ]
     */
    public static function getCategoryTree(): array
    {
        if (self::$categoryTree !== null) {
            return self::$categoryTree;
        }
        
        $items = self::loadItemsData();
        $tree = [];
        
        foreach ($items as $itemId => $item) {
            $category = $item['category'] ?? [];
            
            // 只处理舰船装备
            if (count($category) < 2 || $category[0] !== '舰船装备') {
                continue;
            }
            
            $level2 = $category[1];
            $level3 = $category[2] ?? null;
            $level4 = $category[3] ?? null;
            
            // 初始化二级分类
            if (!isset($tree[$level2])) {
                $tree[$level2] = [];
            }
            
            // 如果有三级分类
            if ($level3) {
                if (!isset($tree[$level2][$level3])) {
                    $tree[$level2][$level3] = [];
                }
                
                // 如果有四级分类（通常是尺寸）
                if ($level4 && !in_array($level4, $tree[$level2][$level3])) {
                    $tree[$level2][$level3][] = $level4;
                }
            }
        }
        
        // 排序四级分类（按尺寸顺序）
        $sizeOrder = ['微型', '小型', '中型', '大型', '旗舰', '超大型'];
        foreach ($tree as $l2 => &$l3Data) {
            foreach ($l3Data as $l3 => &$sizes) {
                if (!empty($sizes)) {
                    $sorted = [];
                    foreach ($sizeOrder as $size) {
                        if (in_array($size, $sizes)) {
                            $sorted[] = $size;
                        }
                    }
                    // 添加其他未在预设顺序中的四级分类
                    foreach ($sizes as $size) {
                        if (!in_array($size, $sorted)) {
                            $sorted[] = $size;
                        }
                    }
                    $sizes = $sorted;
                }
            }
        }
        
        self::$categoryTree = $tree;
        return $tree;
    }
    
    /**
     * 获取指定二级分类下的三级分类列表
     * 
     * @param string $level2 二级分类名（如"护盾"）
     * @return array ['护盾回充器', '护盾扩展装置', ...]
     */
    public static function getLevel3Categories(string $level2): array
    {
        $tree = self::getCategoryTree();
        return isset($tree[$level2]) ? array_keys($tree[$level2]) : [];
    }
    
    /**
     * 获取指定三级分类下的四级分类列表
     * 
     * @param string $level2 二级分类名
     * @param string $level3 三级分类名
     * @return array ['小型', '中型', '大型', ...]
     */
    public static function getLevel4Categories(string $level2, string $level3): array
    {
        $tree = self::getCategoryTree();
        return isset($tree[$level2][$level3]) ? $tree[$level2][$level3] : [];
    }
    
    /**
     * 获取指定分类下的所有物品
     * 
     * @param array $categoryPath 分类路径 ['舰船装备', '护盾', '护盾扩展装置', '小型']
     * @return array [{id, name, category}]
     */
    public static function getItemsByCategory(array $categoryPath): array
    {
        $items = self::loadItemsData();
        $result = [];
        
        foreach ($items as $itemId => $item) {
            $category = $item['category'] ?? [];
            
            // 检查分类路径是否匹配
            $matches = true;
            foreach ($categoryPath as $i => $catName) {
                if (!isset($category[$i]) || $category[$i] !== $catName) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                $result[] = [
                    'id' => (int) $itemId,
                    'name' => $item['name'],
                    'category' => $category
                ];
            }
        }
        
        // 按名称排序
        usort($result, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $result;
    }
    
    /**
     * 搜索装备（按名称模糊匹配）
     * 
     * @param string $query 搜索关键词
     * @param array $categoryFilter 分类过滤 ['舰船装备', '护盾'] 或 []
     * @param int $limit 最大返回数
     * @return array [{id, name, category}]
     */
    public static function searchItems(string $query, array $categoryFilter = [], int $limit = 50): array
    {
        $items = self::loadItemsData();
        $queryLower = mb_strtolower($query, 'UTF-8');
        $result = [];
        
        foreach ($items as $itemId => $item) {
            $category = $item['category'] ?? [];
            
            // 只处理舰船装备
            if (count($category) < 1 || $category[0] !== '舰船装备') {
                continue;
            }
            
            // 分类过滤
            if (!empty($categoryFilter)) {
                $matchesFilter = true;
                foreach ($categoryFilter as $i => $filterCat) {
                    if (!isset($category[$i]) || $category[$i] !== $filterCat) {
                        $matchesFilter = false;
                        break;
                    }
                }
                if (!$matchesFilter) continue;
            }
            
            // 名称匹配
            $nameLower = mb_strtolower($item['name'], 'UTF-8');
            if (mb_strpos($nameLower, $queryLower) === false) {
                continue;
            }
            
            $result[] = [
                'id' => (int) $itemId,
                'name' => $item['name'],
                'category' => $category
            ];
            
            if (count($result) >= $limit) break;
        }
        
        return $result;
    }
    
    /**
     * 获取物品的分类信息
     * 
     * @param int $typeId 物品ID
     * @return array|null 分类路径 ['舰船装备', '护盾', '护盾扩展装置', '小型']
     */
    public static function getItemCategory(int $typeId): ?array
    {
        $items = self::loadItemsData();
        $key = (string) $typeId;
        
        if (isset($items[$key])) {
            return $items[$key]['category'] ?? null;
        }
        
        return null;
    }
    
    /**
     * 获取物品的槽位类型（基于分类推断）
     * 
     * @param array $category 分类路径
     * @return string|null 'high'|'med'|'low'|'rig'|'drone'|'subsystem'
     */
    public static function inferSlotType(array $category): ?string
    {
        if (count($category) < 2) return null;
        
        $level2 = $category[1];
        
        // 高槽装备
        $highSlotCategories = ['炮台和发射器', '采集设备'];
        if (in_array($level2, $highSlotCategories)) return 'high';
        
        // 中槽装备
        $medSlotCategories = ['护盾', '推进器', '电子战', '扫描设备', '电子学和感应器升级', '立体炸弹'];
        if (in_array($level2, $medSlotCategories)) return 'med';
        
        // 低槽装备
        $lowSlotCategories = ['工程装备', '船体和装甲', '无人机升级模块'];
        if (in_array($level2, $lowSlotCategories)) return 'low';
        
        // 改装件
        if ($category[0] === '改装件') return 'rig';
        
        // 无人机
        if ($category[0] === '无人机') return 'drone';
        
        // 子系统
        if ($category[0] === '子系统') return 'subsystem';
        
        return null;
    }
    
    /**
     * 获取所有二级分类（舰船装备的子分类）
     * 
     * @return array ['工程装备', '护盾', '推进器', ...]
     */
    public static function getAllLevel2Categories(): array
    {
        $tree = self::getCategoryTree();
        return array_keys($tree);
    }
    
    /**
     * 获取分类树的API友好格式（用于前端）
     */
    public static function getCategoryTreeForApi(): array
    {
        return Cache::remember('equipment_category_tree', 3600, function() {
            return self::getCategoryTree();
        });
    }
}