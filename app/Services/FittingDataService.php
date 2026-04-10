<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 装配数据服务 - 查询 SDE 数据
 * 
 * 从 fitting.sqlite 查询舰船/装备数据
 */
class FittingDataService
{
    /**
     * 检查装配数据库是否已初始化
     */
    private bool $dbInitialized = false;
    
    /**
     * 检查数据库是否可用
     */
    private function isDatabaseReady(): bool
    {
        if ($this->dbInitialized) {
            return true;
        }
        
        try {
            // 检查关键表是否存在
            $tables = DB::connection('fitting')
                ->select("SELECT name FROM sqlite_master WHERE type='table' AND name='fitting_types'");
            
            if (!empty($tables)) {
                $this->dbInitialized = true;
                return true;
            }
        } catch (\Exception $e) {
            // 数据库不可用
        }
        
        return false;
    }

    /**
     * 装备槽位效果 ID 映射
     */
    private const SLOT_EFFECT_IDS = [
        'high' => [12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64],
        'med' => [13, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 178, 179, 180, 181, 182, 183, 184, 185, 186, 187, 188, 189, 190, 191, 192, 193, 194, 195, 196, 197, 198, 199, 200],
        'low' => [10, 11, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220, 221, 222, 223, 224, 225, 226, 227, 228, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240, 241, 242, 243, 244, 245, 246, 247, 248, 249, 250, 251, 252, 253, 254, 255, 256, 257, 258, 259, 260, 261, 262, 263, 264, 265, 266, 267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277, 278, 279, 280, 281, 282, 283, 284, 285, 286, 287, 288, 289, 290, 291, 292, 293, 294, 295, 296, 297, 298, 299, 300],
        'rig' => [2663],
        'subsystem' => [3772, 3773, 3774, 3775, 3776, 3777, 3778, 3779, 3780, 3781, 3782, 3783, 3784, 3785, 3786, 3787, 3788, 3789, 3790, 3791, 3792, 3793, 3794, 3795, 3796, 3797, 3798, 3799, 3800, 3801, 3802, 3803, 3804, 3805, 3806, 3807, 3808, 3809, 3810, 3811, 3812, 3813, 3814, 3815, 3816, 3817, 3818, 3819, 3820, 3821, 3822, 3823, 3824, 3825, 3826, 3827, 3828, 3829, 3830, 3831, 3832, 3833, 3834, 3835, 3836, 3837, 3838, 3839, 3840, 3841, 3842, 3843, 3844, 3845, 3846, 3847, 3848, 3849, 3850, 3851, 3852, 3853, 3854, 3855, 3856, 3857, 3858, 3859, 3860, 3861, 3862, 3863, 3864, 3865, 3866, 3867, 3868, 3869, 3870, 3871, 3872],
    ];

    /**
     * 属性 ID 映射（常用属性）
     */
    private const ATTR_MAP = [
        // 资源
        48 => 'cpuOutput',
        11 => 'powerOutput',
        482 => 'capacitorCapacity',
        55 => 'rechargeRate',
        1132 => 'upgradeCapacity',
        50 => 'cpu',
        30 => 'power',
        1153 => 'upgradeCost',
        // 槽位
        12 => 'lowSlots',
        13 => 'medSlots',
        14 => 'hiSlots',
        1137 => 'rigSlots',
        102 => 'turretSlotsLeft',
        101 => 'launcherSlotsLeft',
        // HP 与抗性
        263 => 'shieldCapacity',
        265 => 'armorHP',
        9 => 'hp',
        271 => 'shieldEmDamageResonance',
        272 => 'shieldExplosiveDamageResonance',
        273 => 'shieldKineticDamageResonance',
        274 => 'shieldThermalDamageResonance',
        267 => 'armorEmDamageResonance',
        268 => 'armorExplosiveDamageResonance',
        269 => 'armorKineticDamageResonance',
        270 => 'armorThermalDamageResonance',
        // 机动性
        37 => 'maxVelocity',
        70 => 'agility',
        552 => 'signatureRadius',
        600 => 'warpSpeedMultiplier',
        // 锁定
        76 => 'maxTargetRange',
        564 => 'scanResolution',
        192 => 'maxLockedTargets',
        // 无人机
        1271 => 'droneBandwidth',
        283 => 'droneCapacity',
    ];

    /**
     * 舰船组中文名修正
     */
    private const SHIP_GROUP_NAME_MAP = [
        25 => '护卫舰',
        237 => '轻型护卫舰',
        324 => '突击护卫舰',
        420 => '驱逐舰',
        541 => '拦截驱逐舰',
        26 => '巡洋舰',
        358 => '重型突击巡洋舰',
        832 => '后勤巡洋舰',
        833 => '武装侦察舰',
        906 => '隐秘侦察舰',
        894 => '重型拦截巡洋舰',
        963 => '战略巡洋舰',
        419 => '战列巡洋舰',
        540 => '指挥舰',
        1201 => '攻击战列巡洋舰',
        27 => '战列舰',
        898 => '黑隐特勤舰',
        900 => '掠夺舰',
        28 => '工业舰',
        380 => '运输舰',
        513 => '货舰',
        902 => '战略货舰',
        1202 => '封锁运输舰',
        941 => '工业指挥舰',
        883 => '旗舰工业舰',
        463 => '采矿驳船',
        543 => '采掘者',
        830 => '隐形特勤舰',
        831 => '拦截舰',
        834 => '隐形轰炸机',
        893 => '电子攻击舰',
        1283 => '远征护卫舰',
        1527 => '后勤护卫舰',
        1305 => '战术驱逐舰',
        1534 => '指挥驱逐舰',
        1972 => '旗舰巡洋舰',
        30 => '泰坦',
        485 => '无畏舰',
        4594 => '长矛无畏舰',
        547 => '航母',
        659 => '超级航母',
        1538 => '战力辅助舰',
        29 => '太空舱',
        31 => '穿梭机',
        1022 => '特别版舰船',
    ];

    /**
     * 装备二级分类到槽位的映射
     */
    private const MODULE_SLOT_CATEGORY_MAP = [
        '炮台和发射器' => 'high',
        '立体炸弹' => 'high',
        '炸弹' => 'high',
        '采集设备' => 'high',
        '扫描设备' => 'high',
        '舰队辅助装备' => 'high',
        '旗舰级装备' => 'high',
        '旗舰级武器' => 'high',
        '超级武器' => 'high',
        '护盾' => 'med',
        '电子战' => 'med',
        '电子学和感应器升级' => 'med',
        '推进器' => 'med',
        '远程装甲维修器' => 'med',
        '远程护盾传输装置' => 'med',
        '远程电容传输装置' => 'med',
        '指挥突发' => 'med',
        '作战网络' => 'med',
        '船体和装甲' => 'low',
        '工程装备' => 'low',
        '无人机升级模块' => 'low',
        '压缩装置' => 'low',
        '工程改装件' => 'rig',
        '护盾改装件' => 'rig',
        '装甲改装件' => 'rig',
        '武器改装件' => 'rig',
        '电子改装件' => 'rig',
        '无人机改装件' => 'rig',
        '旗舰改装件' => 'rig',
        '采矿改装件' => 'rig',
        '导航改装件' => 'rig',
        '导弹改装件' => 'rig',
        '扫描改装件' => 'rig',
        '瞄准改装件' => 'rig',
        '锚定改装件' => 'rig',
        '改装件' => 'rig',
        '无人机' => 'drone',
    ];

    /**
     * 获取舰船列表（按分类树形结构）
     */
    public function getShipTree(): array
    {
        $cacheKey = 'fitting_ship_tree';
        
        return Cache::remember($cacheKey, 3600, function () {
            // 查询舰船分类（category_id = 6）
            $groups = DB::connection('fitting')
                ->table('fitting_groups')
                ->where('category_id', 6)
                ->where('published', 1)
                ->orderBy('name_en')
                ->get();

            $tree = [];
            
            foreach ($groups as $group) {
                $ships = DB::connection('fitting')
                    ->table('fitting_types')
                    ->where('group_id', $group->group_id)
                    ->where('published', 1)
                    ->orderBy('name_en')
                    ->get();

                if ($ships->isEmpty()) continue;

                $shipList = [];
                foreach ($ships as $ship) {
                    // 获取基础属性（槽位数等）
                    $attrs = $this->getTypeAttributes($ship->type_id);
                    
                    $shipList[] = [
                        'type_id' => $ship->type_id,
                        'name' => $ship->name_en,
                        'name_cn' => $ship->name_cn ?? $ship->name_en,
                        'group_id' => $group->group_id,
                        'group_name' => $group->name_en,
                        'group_name_cn' => $group->name_cn ?? $group->name_en,
                        'slots' => [
                            'hi' => (int)($attrs[14] ?? 0),
                            'med' => (int)($attrs[13] ?? 0),
                            'low' => (int)($attrs[12] ?? 0),
                            'rig' => (int)($attrs[1137] ?? 0),
                        ],
                        'hardpoints' => [
                            'turret' => (int)($attrs[102] ?? 0),
                            'launcher' => (int)($attrs[101] ?? 0),
                        ],
                        'image_url' => $this->getImageUrl($ship->type_id, 128, 6),
                    ];
                }

                $tree[] = [
                    'group_id' => $group->group_id,
                    'group_name' => $group->name_en,
                    'group_name_cn' => $group->name_cn ?? $group->name_en,
                    'ships' => $shipList,
                ];
            }

            return $tree;
        });
    }

    /**
     * 获取舰船分类列表（一级分类=舰种，二级分类=势力）
     */
    public function getShipCategories(): array
    {
        // 检查数据库是否可用
        if (!$this->isDatabaseReady()) {
            return ['not_initialized' => [
                'key' => 'not_initialized',
                'name' => '数据未初始化',
                'count' => 0,
            ]];
        }
        
        return Cache::remember('fitting_ship_categories_v5', 3600, function () {
            $result = [];
            
            // 势力名称映射
            $factionNames = [
                1 => '加达里',
                8 => '盖伦特',
                2 => '米玛塔尔',
                4 => '艾玛',
                0 => '非帝国',
            ];
            
            // 获取所有舰船groups（排除特别版）
            $groups = DB::connection('fitting')
                ->table('fitting_groups')
                ->where('category_id', 6)
                ->where('published', 1)
                ->where('group_id', '!=', 1022)
                ->orderBy('group_id')
                ->get();
            
            foreach ($groups as $group) {
                // 获取该group下每个势力的舰船数量
                $factionCounts = DB::connection('fitting')
                    ->table('fitting_types')
                    ->where('group_id', $group->group_id)
                    ->where('published', 1)
                    ->select('faction_id', DB::raw('count(*) as count'))
                    ->groupBy('faction_id')
                    ->get()
                    ->pluck('count', 'faction_id')
                    ->toArray();
                
                $totalCount = array_sum($factionCounts);
                
                if ($totalCount > 0) {
                    $key = 'group_' . $group->group_id;
                    
                    // 构建势力子分类
                    $factions = [];
                    foreach ($factionNames as $factionId => $factionName) {
                        if (isset($factionCounts[$factionId]) && $factionCounts[$factionId] > 0) {
                            $factions['faction_' . $factionId] = [
                                'key' => 'faction_' . $factionId,
                                'faction_id' => $factionId,
                                'name' => $factionName,
                                'count' => $factionCounts[$factionId],
                            ];
                        }
                    }
                    
                    $result[$key] = [
                        'key' => $key,
                        'group_id' => $group->group_id,
                        'name' => $this->getShipGroupName((int)$group->group_id, $group->name_cn ?? $group->name_en),
                        'count' => $totalCount,
                        'factions' => $factions,
                    ];
                }
            }
            
            // 特别版舰船单独分类（无势力分组）
            $specialCount = DB::connection('fitting')
                ->table('fitting_types')
                ->where('group_id', 1022)
                ->where('published', 1)
                ->count();
            
            if ($specialCount > 0) {
                $result['special'] = [
                    'key' => 'special',
                    'group_id' => 1022,
                    'name' => '特别版舰船',
                    'count' => $specialCount,
                    'factions' => [],
                ];
            }

            return $result;
        });
    }

    /**
     * 获取装备分类列表（用于左侧树形导航）
     * 按 eve_items.json 的实际分类组织
     */
    public function getModuleCategories(): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }
        
        return Cache::remember('fitting_module_categories', 3600, function () {
            $result = [];
            
            // 按eve_items.json的实际分类组织
            // 护盾类装备（中槽）
            $shieldGroups = $this->getGroupsForCategory('护盾', 'med');
            if (!empty($shieldGroups)) {
                $result['shield'] = [
                    'key' => 'shield',
                    'name' => '护盾',
                    'slot' => 'med',
                    'groups' => $shieldGroups,
                ];
            }
            
            // 装甲类装备（低槽）
            $armorGroups = $this->getGroupsForCategory('船体和装甲', 'low');
            if (!empty($armorGroups)) {
                $result['armor'] = [
                    'key' => 'armor',
                    'name' => '装甲',
                    'slot' => 'low',
                    'groups' => $armorGroups,
                ];
            }
            
            // 电子战（中槽）
            $ewarGroups = $this->getGroupsForCategory('电子战', 'med');
            if (!empty($ewarGroups)) {
                $result['ewar'] = [
                    'key' => 'ewar',
                    'name' => '电子战',
                    'slot' => 'med',
                    'groups' => $ewarGroups,
                ];
            }
            
            // 电子学和感应器升级（低槽）
            $electronicsGroups = $this->getGroupsForCategory('电子学和感应器升级', 'low');
            if (!empty($electronicsGroups)) {
                $result['electronics'] = [
                    'key' => 'electronics',
                    'name' => '电子学',
                    'slot' => 'low',
                    'groups' => $electronicsGroups,
                ];
            }
            
            // 工程装备（低槽）
            $engineeringGroups = $this->getGroupsForCategory('工程装备', 'low');
            if (!empty($engineeringGroups)) {
                $result['engineering'] = [
                    'key' => 'engineering',
                    'name' => '工程装备',
                    'slot' => 'low',
                    'groups' => $engineeringGroups,
                ];
            }
            
            // 炮台和发射器（高槽）
            $weaponsGroups = $this->getGroupsForCategory('炮台和发射器', 'high');
            if (!empty($weaponsGroups)) {
                $result['weapons'] = [
                    'key' => 'weapons',
                    'name' => '武器系统',
                    'slot' => 'high',
                    'groups' => $weaponsGroups,
                ];
            }
            
            // 推进器（中槽）
            $propulsionGroups = $this->getGroupsForCategory('推进器', 'med');
            if (!empty($propulsionGroups)) {
                $result['propulsion'] = [
                    'key' => 'propulsion',
                    'name' => '推进器',
                    'slot' => 'med',
                    'groups' => $propulsionGroups,
                ];
            }
            
            // 无人机升级模块（低槽）
            $droneUpgradeGroups = $this->getGroupsForCategory('无人机升级模块', 'low');
            if (!empty($droneUpgradeGroups)) {
                $result['drone_upgrade'] = [
                    'key' => 'drone_upgrade',
                    'name' => '无人机升级',
                    'slot' => 'low',
                    'groups' => $droneUpgradeGroups,
                ];
            }
            
            // 采集设备（高槽）
            $miningGroups = $this->getGroupsForCategory('采集设备', 'high');
            if (!empty($miningGroups)) {
                $result['mining'] = [
                    'key' => 'mining',
                    'name' => '采集设备',
                    'slot' => 'high',
                    'groups' => $miningGroups,
                ];
            }
            
            // 扫描设备（高槽）
            $scanningGroups = $this->getGroupsForCategory('扫描设备', 'high');
            if (!empty($scanningGroups)) {
                $result['scanning'] = [
                    'key' => 'scanning',
                    'name' => '扫描设备',
                    'slot' => 'high',
                    'groups' => $scanningGroups,
                ];
            }
            
            // 立体炸弹（高槽）
            $smartbombGroups = $this->getGroupsForCategory('立体炸弹', 'high');
            if (!empty($smartbombGroups)) {
                $result['smartbomb'] = [
                    'key' => 'smartbomb',
                    'name' => '立体炸弹',
                    'slot' => 'high',
                    'groups' => $smartbombGroups,
                ];
            }
            
            // 舰队辅助装备（高槽）
            $fleetGroups = $this->getGroupsForCategory('舰队辅助装备', 'high');
            if (!empty($fleetGroups)) {
                $result['fleet'] = [
                    'key' => 'fleet',
                    'name' => '舰队装备',
                    'slot' => 'high',
                    'groups' => $fleetGroups,
                ];
            }
            
            // 改装件分类
            $rigCategories = [
                '工程改装件' => '工程改装',
                '护盾改装件' => '护盾改装',
                '装甲改装件' => '装甲改装',
                '武器改装件' => '武器改装',
                '电子改装件' => '电子改装',
                '无人机改装件' => '无人机改装',
                '导航改装件' => '导航改装',
                '扫描改装件' => '扫描改装',
                '瞄准改装件' => '瞄准改装',
            ];
            
            foreach ($rigCategories as $catName => $displayName) {
                $rigGroups = $this->getGroupsForCategory($catName, 'rig');
                if (!empty($rigGroups)) {
                    $key = str_replace(['改装件', '改装'], '', $catName) . '_rig';
                    $result[$key] = [
                        'key' => $key,
                        'name' => $displayName,
                        'slot' => 'rig',
                        'groups' => $rigGroups,
                    ];
                }
            }
            
            // 无人机分类（category_id = 18）
            $droneGroups = DB::connection('fitting')
                ->table('fitting_groups')
                ->where('category_id', 18)
                ->where('published', 1)
                ->orderBy('name_en')
                ->get();
            
            $droneGroupList = [];
            foreach ($droneGroups as $group) {
                $count = DB::connection('fitting')
                    ->table('fitting_types')
                    ->where('group_id', $group->group_id)
                    ->where('published', 1)
                    ->count();
                
                if ($count > 0) {
                    $droneGroupList[] = [
                        'group_id' => $group->group_id,
                        'name' => $group->name_en,
                        'name_cn' => $group->name_cn ?? $group->name_en,
                        'count' => $count,
                        'slot' => 'drone',
                    ];
                }
            }
            
            if (!empty($droneGroupList)) {
                $result['drone'] = [
                    'key' => 'drone',
                    'name' => '无人机',
                    'slot' => 'drone',
                    'groups' => $droneGroupList,
                ];
            }
            
            return $result;
        });
    }
    
    /**
     * 根据分类名获取分组列表
     */
    private function getGroupsForCategory(string $categoryName, string $slotType): array
    {
        // 这个方法需要从eve_items.json获取分类信息
        // 由于分类信息在JSON中，这里使用预设的group_id映射
        $categoryGroupMap = [
            '护盾' => [38, 39, 40, 41, 57, 77, 295, 338, 770, 1156, 1697],
            '船体和装甲' => [60, 62, 63, 78, 98, 326, 328, 329, 325, 1150, 1199, 1698, 1699, 585, 762, 763, 765],
            '电子战' => [52, 65, 80, 201, 208, 379, 842, 899, 589, 291],
            '电子学和感应器升级' => [82, 96, 210, 212, 285, 290, 330, 481, 538, 650],
            '工程装备' => [43, 61, 67, 68, 71, 76, 339, 766, 767, 768, 769],
            '炮台和发射器' => [53, 55, 74, 205, 209, 211, 213, 302, 367, 506, 507, 508, 509, 510, 511, 512, 524, 771, 862, 1245, 1673, 1674, 515, 588],
            '推进器' => [46, 315, 764],
            '无人机升级模块' => [407, 644, 645, 646, 647, 1292],
            '采集设备' => [54, 464, 483, 546, 737],
            '扫描设备' => [47, 48, 49, 481, 538],
            '立体炸弹' => [72],
            '舰队辅助装备' => [590, 658, 815],
            '工程改装件' => [781],
            '护盾改装件' => [774],
            '装甲改装件' => [773],
            '武器改装件' => [775, 776, 777, 779],
            '电子改装件' => [786],
            '无人机改装件' => [778],
            '导航改装件' => [782],
            '扫描改装件' => [1233],
            '瞄准改装件' => [1234],
        ];
        
        $groupIds = $categoryGroupMap[$categoryName] ?? [];
        if (empty($groupIds)) {
            return [];
        }
        
        $groups = DB::connection('fitting')
            ->table('fitting_groups')
            ->whereIn('group_id', $groupIds)
            ->where('published', 1)
            ->orderBy('name_en')
            ->get();
        
        $result = [];
        foreach ($groups as $group) {
            $count = DB::connection('fitting')
                ->table('fitting_types')
                ->where('group_id', $group->group_id)
                ->where('published', 1)
                ->count();
            
            if ($count > 0) {
                $result[] = [
                    'group_id' => $group->group_id,
                    'name' => $group->name_en,
                    'name_cn' => $group->name_cn ?? $group->name_en,
                    'count' => $count,
                    'slot' => $slotType,
                ];
            }
        }
        
        return $result;
    }

    /**
     * 获取指定分组的装备列表
     */
    public function getModulesByGroup(int $groupId, ?string $slotType = null): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }
        
        $cacheKey = "fitting_modules_group_{$groupId}" . ($slotType ? "_{$slotType}" : "");
        
        return Cache::remember($cacheKey, 3600, function () use ($groupId, $slotType) {
            $query = DB::connection('fitting')
                ->table('fitting_types')
                ->where('group_id', $groupId)
                ->where('published', 1);
            
            // 如果指定了槽位类型，需要通过效果过滤
            if ($slotType && isset(self::SLOT_EFFECT_IDS[$slotType])) {
                $effectIds = self::SLOT_EFFECT_IDS[$slotType];
                $query->whereIn('type_id', function ($subQ) use ($effectIds) {
                    $subQ->select('type_id')
                         ->from('fitting_type_effects')
                         ->whereIn('effect_id', $effectIds);
                });
            }
            
            $modules = $query->orderBy('name_en')->get();
            
            $result = [];
            foreach ($modules as $mod) {
                $attrs = $this->getTypeAttributes($mod->type_id);
                $slot = $slotType ?: $this->determineModuleSlot($mod->type_id);
                
                $result[] = [
                    'type_id' => $mod->type_id,
                    'name' => $mod->name_en,
                    'name_cn' => $mod->name_cn ?? $mod->name_en,
                    'group_id' => $mod->group_id,
                    'slot' => $slot,
                    'cpu' => (float)($attrs[50] ?? 0),
                    'power' => (float)($attrs[30] ?? 0),
                    'upgrade_cost' => (float)($attrs[1153] ?? 0),
                    'meta_level' => (int)($attrs[422] ?? 0),
                    'image_url' => $this->getImageUrl($mod->type_id, 128, (int)$mod->category_id),
                ];
            }
            
            return $result;
        });
    }

    /**
     * 获取舰船详情（包含所有属性）
     */
    public function getShipDetails(int $typeId): ?array
    {
        return Cache::remember("fitting_ship_{$typeId}", 3600, function () use ($typeId) {
            $type = DB::connection('fitting')
                ->table('fitting_types')
                ->where('type_id', $typeId)
                ->first();

            if (!$type) return null;

            $attrs = $this->getTypeAttributes($typeId);
            $effects = $this->getTypeEffects($typeId);
            $attrDefs = $this->getAttributeDefinitions();

            // 构建属性数据（带名称）
            $namedAttrs = [];
            foreach ($attrs as $attrId => $value) {
                $name = self::ATTR_MAP[$attrId] ?? $attrDefs[$attrId]['name'] ?? "attr_{$attrId}";
                $namedAttrs[$name] = [
                    'attribute_id' => $attrId,
                    'value' => $value,
                    'name' => $name,
                    'unit' => $attrDefs[$attrId]['unit'] ?? null,
                ];
            }

            return [
                'type_id' => $typeId,
                'name' => $type->name_en,
                'name_cn' => $type->name_cn ?? $type->name_en,
                'group_id' => $type->group_id,
                'category_id' => $type->category_id,
                'description' => $type->description,
                'mass' => $type->mass,
                'volume' => $type->volume,
                'capacity' => $type->capacity,
                'slots' => [
                    'hi' => (int)($attrs[14] ?? 0),
                    'med' => (int)($attrs[13] ?? 0),
                    'low' => (int)($attrs[12] ?? 0),
                    'rig' => (int)($attrs[1137] ?? 0),
                ],
                'hardpoints' => [
                    'turret' => (int)($attrs[102] ?? 0),
                    'launcher' => (int)($attrs[101] ?? 0),
                ],
                'resources' => [
                    'cpu_output' => (float)($attrs[48] ?? 0),
                    'power_output' => (float)($attrs[11] ?? 0),
                    'capacitor_capacity' => (float)($attrs[482] ?? 0),
                    'recharge_rate' => (float)($attrs[55] ?? 0),
                    'upgrade_capacity' => (float)($attrs[1132] ?? 350),
                ],
                'attributes' => $namedAttrs,
                'effects' => $effects,
                'image_url' => $this->getImageUrl($typeId, 128, 6),
                'render_url' => $this->getShipRenderUrl($typeId, 256),
            ];
        });
    }

    /**
     * 获取物品属性值
     */
    public function getTypeAttributes(int $typeId): array
    {
        $rows = DB::connection('fitting')
            ->table('fitting_attributes')
            ->where('type_id', $typeId)
            ->get();

        $attrs = [];
        foreach ($rows as $row) {
            // 优先使用 float，如果没有则用 int
            $value = $row->value_float ?? $row->value_int ?? 0;
            $attrs[$row->attribute_id] = $value;
        }

        return $attrs;
    }

    /**
     * 获取物品效果列表
     */
    public function getTypeEffects(int $typeId): array
    {
        $rows = DB::connection('fitting')
            ->table('fitting_type_effects')
            ->join('fitting_effects', 'fitting_type_effects.effect_id', '=', 'fitting_effects.effect_id')
            ->where('fitting_type_effects.type_id', $typeId)
            ->select('fitting_effects.*', 'fitting_type_effects.is_default')
            ->get();

        $effects = [];
        foreach ($rows as $row) {
            $modifiers = null;
            if ($row->modifiers_json) {
                $modifiers = json_decode($row->modifiers_json, true);
            }

            $effects[] = [
                'effect_id' => $row->effect_id,
                'name' => $row->name,
                'effect_category' => $row->effect_category,
                'is_assistance' => $row->is_assistance,
                'is_offensive' => $row->is_offensive,
                'is_default' => $row->is_default,
                'modifiers' => $modifiers,
            ];
        }

        return $effects;
    }

    /**
     * 获取属性定义
     */
    public function getAttributeDefinitions(): array
    {
        return Cache::remember('fitting_attr_definitions', 3600, function () {
            $rows = DB::connection('fitting')
                ->table('fitting_attribute_types')
                ->get();

            $defs = [];
            foreach ($rows as $row) {
                $defs[$row->attribute_id] = [
                    'name' => $row->name,
                    'description' => $row->description,
                    'unit' => $this->getUnitName($row->unit_id),
                    'stackable' => $row->stackable,
                    'high_is_good' => $row->high_is_good,
                ];
            }

            return $defs;
        });
    }

    /**
     * 获取单位名称
     */
    private function getUnitName(?int $unitId): ?string
    {
        $units = [
            1 => 'm',
            2 => 'kg',
            3 => 'GJ',
            4 => 'tf',
            5 => 'MW',
            6 => 'm/s',
            7 => 's',
            8 => '%',
            9 => 'ISK',
            10 => 'm³',
            11 => 'GJ/s',
            12 => 'MW/s',
            13 => 'm³/s',
            14 => 'hp',
            15 => 'N/A',
        ];

        return $units[$unitId] ?? null;
    }

    /**
     * 搜索装备
     */
    public function searchModules(string $query, ?string $slotType = null, int $limit = 50): array
    {
        $db = DB::connection('fitting');

        // 基础查询：装备分类 (category_id = 7)
        $q = $db->table('fitting_types')
            ->where('category_id', 7)
            ->where('published', 1);

        // 名称搜索
        if ($query) {
            $q->where(function ($subQ) use ($query) {
                $subQ->where('name_en', 'like', "%{$query}%")
                     ->orWhere('name_cn', 'like', "%{$query}%");
            });
        }

        $candidateLimit = $query ? max($limit * 4, 120) : max($limit * 10, 400);
        $results = $q->orderBy('name_en')
                     ->limit($candidateLimit)
                     ->get();

        $modules = [];
        foreach ($results as $mod) {
            $attrs = $this->getTypeAttributes($mod->type_id);
            $slot = $this->determineModuleSlot($mod->type_id);

            if ($slotType && $slot !== $slotType) {
                continue;
            }

            $categoryPath = $this->getLocalItemCategoryPath((int)$mod->type_id);

            $modules[] = [
                'type_id' => $mod->type_id,
                'name' => $mod->name_en,
                'name_cn' => $mod->name_cn ?? $mod->name_en,
                'group_id' => $mod->group_id,
                'slot' => $slot,
                'category_label' => $this->buildModuleCategoryLabel($categoryPath),
                'category_path' => $categoryPath,
                'cpu' => (float)($attrs[50] ?? 0),
                'power' => (float)($attrs[30] ?? 0),
                'upgrade_cost' => (float)($attrs[1153] ?? 0),
                'meta_level' => (int)($attrs[422] ?? 0),
                'image_url' => $this->getImageUrl($mod->type_id, 128, (int)$mod->category_id),
            ];

            if (count($modules) >= $limit) {
                break;
            }
        }

        return $modules;
    }

    public function getShipCategoryTree(): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }

        return $this->getShipCatalog()['tree'];
    }

    public function getShipsByCategoryPath(array $path): array
    {
        if (!$this->isDatabaseReady() || empty($path)) {
            return [];
        }

        $catalog = $this->getShipCatalog();
        $typeIds = $catalog['path_map'][$this->pathKey($path)] ?? [];
        if (empty($typeIds)) {
            return [];
        }

        $attrMap = $this->getTypeAttributesMap($typeIds);
        $result = [];
        foreach ($typeIds as $typeId) {
            $ship = $catalog['types'][$typeId] ?? null;
            if (!$ship) {
                continue;
            }

            $attrs = $attrMap[$typeId] ?? [];
            $result[] = [
                'type_id' => $typeId,
                'name' => $ship['name'],
                'name_cn' => $ship['name_cn'],
                'category_path' => $ship['category_path'],
                'faction_id' => (int)($ship['faction_id'] ?? 0),
                'slots' => [
                    'hi' => (int)($attrs[14] ?? 0),
                    'med' => (int)($attrs[13] ?? 0),
                    'low' => (int)($attrs[12] ?? 0),
                    'rig' => (int)($attrs[1137] ?? 0),
                ],
                'image_url' => $this->getImageUrl($typeId, 64, 6),
                'render_url' => $this->getShipRenderUrl($typeId, 256),
            ];
        }

        return $result;
    }

    /**
     * 判断装备槽位类型
     */
    public function determineModuleSlot(int $typeId): ?string
    {
        $slotFromCategory = $this->inferSlotFromCategoryPath($this->getLocalItemCategoryPath($typeId));
        if ($slotFromCategory) {
            return $slotFromCategory;
        }

        $effects = DB::connection('fitting')
            ->table('fitting_type_effects')
            ->where('type_id', $typeId)
            ->pluck('effect_id');

        foreach ($effects as $effectId) {
            foreach (self::SLOT_EFFECT_IDS as $slot => $ids) {
                if (in_array($effectId, $ids)) {
                    return $slot;
                }
            }
        }

        return null;
    }

    /**
     * 获取物品详情（用于装配时加载装备属性）
     */
    public function getTypeDetails(int $typeId): ?array
    {
        return Cache::remember("fitting_type_{$typeId}", 3600, function () use ($typeId) {
            $type = DB::connection('fitting')
                ->table('fitting_types')
                ->where('type_id', $typeId)
                ->first();

            if (!$type) return null;

            $attrs = $this->getTypeAttributes($typeId);
            $effects = $this->getTypeEffects($typeId);
            $categoryPath = $this->getLocalItemCategoryPath($typeId);

            return [
                'type_id' => $typeId,
                'name' => $type->name_en,
                'name_cn' => $type->name_cn ?? $type->name_en,
                'group_id' => $type->group_id,
                'category_id' => $type->category_id,
                'slot' => $this->determineModuleSlot($typeId),
                'category_label' => $this->buildModuleCategoryLabel($categoryPath),
                'category_path' => $categoryPath,
                'attributes' => $attrs,
                'effects' => $effects,
                'image_url' => $this->getImageUrl($typeId, 128, (int)$type->category_id),
            ];
        });
    }

    /**
     * 获取图片 URL（本地优先，CDN 兜底）
     */
    public function getImageUrl(int $typeId, int $size = 128, ?int $categoryId = null): string
    {
        return $this->getRemoteImageUrl($typeId, $size);
    }

    public function getRemoteImageUrl(int $typeId, int $size = 128): string
    {
        return "https://image.evepc.163.com/Type/{$typeId}_{$size}.png";
    }

    public function getShipRenderUrl(int $typeId, int $size = 256): string
    {
        return "https://image.evepc.163.com/Render/{$typeId}_{$size}.png";
    }

    /**
     * 获取组内的舰船列表
     */
    public function getShipsByGroup(int $groupId): array
    {
        return Cache::remember("fitting_ships_group_{$groupId}", 3600, function () use ($groupId) {
            $ships = DB::connection('fitting')
                ->table('fitting_types')
                ->where('group_id', $groupId)
                ->where('published', 1)
                ->orderBy('name_en')
                ->get();

            $result = [];
            foreach ($ships as $ship) {
                $attrs = $this->getTypeAttributes($ship->type_id);
                
                $result[] = [
                    'type_id' => $ship->type_id,
                    'name' => $ship->name_en,
                    'name_cn' => $ship->name_cn ?? $ship->name_en,
                    'faction_id' => (int)($ship->faction_id ?? 0),
                    'slots' => [
                        'hi' => (int)($attrs[14] ?? 0),
                        'med' => (int)($attrs[13] ?? 0),
                        'low' => (int)($attrs[12] ?? 0),
                        'rig' => (int)($attrs[1137] ?? 0),
                    ],
                    'image_url' => $this->getImageUrl($ship->type_id, 128, 6),
                ];
            }

            return $result;
        });
    }

    /**
     * 获取组内指定势力的舰船列表
     */
    public function getShipsByGroupAndFaction(int $groupId, int $factionId): array
    {
        return Cache::remember("fitting_ships_group_{$groupId}_faction_{$factionId}", 3600, function () use ($groupId, $factionId) {
            $ships = DB::connection('fitting')
                ->table('fitting_types')
                ->where('group_id', $groupId)
                ->where('faction_id', $factionId)
                ->where('published', 1)
                ->orderBy('name_en')
                ->get();

            $result = [];
            foreach ($ships as $ship) {
                $attrs = $this->getTypeAttributes($ship->type_id);
                
                $result[] = [
                    'type_id' => $ship->type_id,
                    'name' => $ship->name_en,
                    'name_cn' => $ship->name_cn ?? $ship->name_en,
                    'faction_id' => (int)($ship->faction_id ?? 0),
                    'slots' => [
                        'hi' => (int)($attrs[14] ?? 0),
                        'med' => (int)($attrs[13] ?? 0),
                        'low' => (int)($attrs[12] ?? 0),
                        'rig' => (int)($attrs[1137] ?? 0),
                    ],
                    'image_url' => $this->getImageUrl($ship->type_id, 128, 6),
                ];
            }

            return $result;
        });
    }

    /**
     * 获取基于eve_items.json四级分类的装备分类树
     * 
     * 分类结构：
     * - 一级：舰船装备
     * - 二级：工程装备、护盾、推进器、炮台和发射器等（13个）
     * - 三级：具体装备类型（如电容回充器、护盾扩展装置等）
     * - 四级：尺寸规格（小型/中型/大型/旗舰等）
     * 
     * @param string|null $slotFilter 槽位过滤 'high'|'med'|'low'|'rig'|'drone'
     * @return array 多级分类树
     */
    public function getModuleCategoryTree(?string $slotFilter = null): array
    {
        $catalog = $this->getModuleCatalog();
        $treeKey = $slotFilter ? "tree_{$slotFilter}" : 'tree_all';
        return $catalog[$treeKey] ?? [];
    }

    /**
     * 根据二级分类推断槽位类型
     */
    private function inferSlotFromCategory(string $category): string
    {
        return self::MODULE_SLOT_CATEGORY_MAP[$category] ?? 'unknown';
    }

    private function getShipGroupName(int $groupId, ?string $fallback = null): string
    {
        return self::SHIP_GROUP_NAME_MAP[$groupId] ?? ($fallback ?: "group_{$groupId}");
    }

    private function appendTreePath(array &$tree, array $path, int $depth = 0): void
    {
        if (!isset($path[$depth])) {
            return;
        }

        $name = $path[$depth];
        if (!isset($tree[$name])) {
            $tree[$name] = [
                'name' => $name,
                'path' => array_slice($path, 0, $depth + 1),
                'count' => 0,
                'children' => [],
            ];
        }

        $tree[$name]['count']++;

        if (isset($path[$depth + 1])) {
            $this->appendTreePath($tree[$name]['children'], $path, $depth + 1);
        }
    }

    private function normalizeTreeNodes(array $tree): array
    {
        $nodes = array_values($tree);

        usort($nodes, function (array $a, array $b) {
            return strcmp($a['name'], $b['name']);
        });

        foreach ($nodes as &$node) {
            $node['children'] = $this->normalizeTreeNodes($node['children'] ?? []);
        }

        return $nodes;
    }

    private function pathStartsWith(array $fullPath, array $prefix): bool
    {
        if (count($prefix) > count($fullPath)) {
            return false;
        }

        foreach ($prefix as $index => $part) {
            if (($fullPath[$index] ?? null) !== $part) {
                return false;
            }
        }

        return true;
    }

    private function getLocalItemsIndex(): array
    {
        static $items = null;

        if ($items !== null) {
            return $items;
        }

        $itemsFile = base_path('data/eve_items.json');
        if (!file_exists($itemsFile)) {
            $items = [];
            return $items;
        }

        $decoded = json_decode(file_get_contents($itemsFile), true);
        $items = is_array($decoded) ? $decoded : [];

        return $items;
    }

    private function getLocalItemCategoryPath(int $typeId): array
    {
        $items = $this->getLocalItemsIndex();
        $item = $items[(string)$typeId] ?? null;
        $category = $item['category'] ?? [];
        return is_array($category) ? array_values($category) : [];
    }

    private function getShipDisplayCategoryPath(int $typeId): array
    {
        $path = $this->getLocalItemCategoryPath($typeId);
        if (empty($path)) {
            return [];
        }

        if (($path[0] ?? null) === '舰船') {
            array_shift($path);
        }

        if (($path[0] ?? null) === '运载舰和工业舰') {
            $path[0] = '工业与运载舰';
        }

        return $path;
    }

    private function getModuleDisplayCategoryPath(int $typeId): array
    {
        $path = $this->getLocalItemCategoryPath($typeId);
        if (empty($path)) {
            return [];
        }

        $level1 = $path[0] ?? null;

        if ($level1 === '舰船装备') {
            return array_slice($path, 1);
        }

        return $path;
    }

    private function inferSlotFromCategoryPath(array $categoryPath): ?string
    {
        if (empty($categoryPath)) {
            return null;
        }

        $level1 = $categoryPath[0] ?? null;
        $level2 = $categoryPath[1] ?? null;

        if ($level1 === '无人机') {
            return 'drone';
        }

        if ($level1 === '舰船装备' && $level2) {
            return self::MODULE_SLOT_CATEGORY_MAP[$level2] ?? null;
        }

        if ($level1 && str_contains($level1, '改装件')) {
            return 'rig';
        }

        return self::MODULE_SLOT_CATEGORY_MAP[$level1] ?? null;
    }

    private function buildModuleCategoryLabel(array $categoryPath): ?string
    {
        if (empty($categoryPath)) {
            return null;
        }

        $level1 = $categoryPath[0] ?? null;
        $level2 = $categoryPath[1] ?? null;
        $level3 = $categoryPath[2] ?? null;

        if ($level1 === '舰船装备') {
            return $level3 ?: $level2;
        }

        if ($level1 && str_contains($level1, '改装件')) {
            return $level2 ?: $level1;
        }

        return $level2 ?: $level1;
    }

    /**
     * 获取指定分类路径下的装备列表
     * 
     * @param array $categoryPath 分类路径 ['舰船装备', '护盾', '护盾扩展装置', '小型']
     * @return array 装备列表
     */
    public function getModulesByCategoryPath(array $categoryPath, ?string $slotFilter = null): array
    {
        if (!$this->isDatabaseReady() || empty($categoryPath)) {
            return [];
        }

        $catalog = $this->getModuleCatalog();
        $typeIds = $catalog['path_map'][$this->pathKey($categoryPath)] ?? [];
        if (empty($typeIds)) {
            return [];
        }

        $attrMap = $this->getTypeAttributesMap($typeIds);
        $result = [];
        foreach ($typeIds as $typeId) {
            $module = $catalog['types'][$typeId] ?? null;
            if (!$module) {
                continue;
            }

            if ($slotFilter && ($module['slot'] ?? null) !== $slotFilter) {
                continue;
            }

            $attrs = $attrMap[$typeId] ?? [];

            $result[] = [
                'type_id' => $typeId,
                'name' => $module['name'],
                'name_cn' => $module['name_cn'],
                'category' => $module['category_path'],
                'category_label' => $this->buildModuleCategoryLabel($module['category_path']),
                'slot' => $module['slot'],
                'cpu' => (float)($attrs[50] ?? 0),
                'power' => (float)($attrs[30] ?? 0),
                'upgrade_cost' => (float)($attrs[1153] ?? 0),
                'meta_level' => (int)($attrs[422] ?? 0),
                'image_url' => $this->getImageUrl($typeId, 64, 7),
            ];
        }
        
        // 按名称排序
        usort($result, function($a, $b) {
            return strcmp($a['name_cn'] ?: $a['name'], $b['name_cn'] ?: $b['name']);
        });
        
        return $result;
    }

    private function getShipCatalog(): array
    {
        return Cache::remember('fitting_ship_catalog_v2', 3600, function () {
            $types = DB::connection('fitting')
                ->table('fitting_types')
                ->where('category_id', 6)
                ->where('published', 1)
                ->select('type_id', 'name_en', 'name_cn', 'faction_id')
                ->orderByRaw('COALESCE(name_cn, name_en)')
                ->get();

            $tree = [];
            $pathMap = [];
            $typeMap = [];

            foreach ($types as $type) {
                $typeId = (int)$type->type_id;
                $path = $this->getShipDisplayCategoryPath($typeId);
                if (empty($path)) {
                    continue;
                }

                $this->appendTreePath($tree, $path);
                $pathMap[$this->pathKey($path)][] = $typeId;
                $typeMap[$typeId] = [
                    'name' => $type->name_en,
                    'name_cn' => $type->name_cn ?? $type->name_en,
                    'faction_id' => (int)($type->faction_id ?? 0),
                    'category_path' => $path,
                ];
            }

            return [
                'tree' => $this->normalizeTreeNodes($tree),
                'path_map' => $pathMap,
                'types' => $typeMap,
            ];
        });
    }

    private function getModuleCatalog(): array
    {
        return Cache::remember('fitting_module_catalog_v2', 3600, function () {
            $types = DB::connection('fitting')
                ->table('fitting_types')
                ->where('category_id', 7)
                ->where('published', 1)
                ->select('type_id', 'name_en', 'name_cn')
                ->orderByRaw('COALESCE(name_cn, name_en)')
                ->get();

            $effectSlotMap = $this->getModuleEffectSlotMap();
            $treeAll = [];
            $treeBySlot = [
                'high' => [],
                'med' => [],
                'low' => [],
                'rig' => [],
                'drone' => [],
            ];
            $pathMap = [];
            $typeMap = [];

            foreach ($types as $type) {
                $typeId = (int)$type->type_id;
                $path = $this->getModuleDisplayCategoryPath($typeId);
                if (empty($path)) {
                    continue;
                }

                $slot = $this->inferSlotFromCategoryPath($this->getLocalItemCategoryPath($typeId))
                    ?? ($effectSlotMap[$typeId] ?? null);

                if (!$slot) {
                    continue;
                }

                $this->appendTreePath($treeAll, $path);
                if (isset($treeBySlot[$slot])) {
                    $this->appendTreePath($treeBySlot[$slot], $path);
                }

                $pathMap[$this->pathKey($path)][] = $typeId;
                $typeMap[$typeId] = [
                    'name' => $type->name_en,
                    'name_cn' => $type->name_cn ?? $type->name_en,
                    'slot' => $slot,
                    'category_path' => $path,
                ];
            }

            return [
                'tree_all' => $this->normalizeTreeNodes($treeAll),
                'tree_high' => $this->normalizeTreeNodes($treeBySlot['high']),
                'tree_med' => $this->normalizeTreeNodes($treeBySlot['med']),
                'tree_low' => $this->normalizeTreeNodes($treeBySlot['low']),
                'tree_rig' => $this->normalizeTreeNodes($treeBySlot['rig']),
                'tree_drone' => $this->normalizeTreeNodes($treeBySlot['drone']),
                'path_map' => $pathMap,
                'types' => $typeMap,
            ];
        });
    }

    private function getModuleEffectSlotMap(): array
    {
        return Cache::remember('fitting_module_effect_slot_map_v1', 3600, function () {
            $rows = DB::connection('fitting')
                ->table('fitting_type_effects')
                ->join('fitting_types', 'fitting_types.type_id', '=', 'fitting_type_effects.type_id')
                ->where('fitting_types.category_id', 7)
                ->where('fitting_types.published', 1)
                ->select('fitting_type_effects.type_id', 'fitting_type_effects.effect_id')
                ->get();

            $slotMap = [];
            foreach ($rows as $row) {
                $typeId = (int)$row->type_id;
                if (isset($slotMap[$typeId])) {
                    continue;
                }

                foreach (self::SLOT_EFFECT_IDS as $slot => $ids) {
                    if (in_array((int)$row->effect_id, $ids, true)) {
                        $slotMap[$typeId] = $slot;
                        break;
                    }
                }
            }

            return $slotMap;
        });
    }

    private function getTypeAttributesMap(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::connection('fitting')
            ->table('fitting_attributes')
            ->whereIn('type_id', $typeIds)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $typeId = (int)$row->type_id;
            $result[$typeId] ??= [];
            $result[$typeId][(int)$row->attribute_id] = $row->value_float ?? $row->value_int ?? 0;
        }

        return $result;
    }

    private function pathKey(array $path): string
    {
        return implode(' > ', $path);
    }
}
