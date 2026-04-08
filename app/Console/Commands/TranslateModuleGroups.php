<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * 翻译装配数据库中的装备分组名称
 * 从 eve_items.json 获取正确的中文分类
 */
class TranslateModuleGroups extends Command
{
    protected $signature = 'fitting:translate-modules
        {--dry-run : 只显示将要翻译的内容，不实际修改}';

    protected $description = '翻译装备分组名称并修正分类';

    private const ITEMS_JSON = '/var/www/html/data/eve_items.json';

    // 模块一级分类映射（eve_items.json 二级分类 -> 槽位类型）
    private const MODULE_CATEGORY_MAP = [
        // 高槽分类
        '炮台和发射器' => 'high',
        '立体炸弹' => 'high',
        '炸弹' => 'high',
        '采集设备' => 'high',
        '扫描设备' => 'high',
        '舰队辅助装备' => 'high',
        '旗舰级装备' => 'high',
        '旗舰级武器' => 'high',
        '超级武器' => 'high',
        
        // 中槽分类
        '护盾' => 'med',
        '电子战' => 'med',
        '电子学和感应器升级' => 'med',
        '推进器' => 'med',
        '远程装甲维修器' => 'med',
        '远程护盾传输装置' => 'med',
        '远程电容传输装置' => 'med',
        '指挥突发' => 'med',
        '作战网络' => 'med',
        
        // 低槽分类
        '船体和装甲' => 'low',
        '工程装备' => 'low',
        '无人机升级模块' => 'low',
        
        // 改装件
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
        
        // 无人机（独立分类）
        '无人机' => 'drone',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fittingDbPath = database_path('fitting.sqlite');

        if (!file_exists($fittingDbPath)) {
            $this->error('fitting.sqlite 数据库不存在');
            return 1;
        }

        // 加载本地JSON
        $localItems = $this->loadLocalJson();
        $this->info('本地JSON加载完成: ' . count($localItems) . ' 个物品');

        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取所有装备分组
        $groups = $pdo->query("
            SELECT group_id, name_en, name_cn 
            FROM fitting_groups 
            WHERE category_id = 7
            ORDER BY group_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->info('找到 ' . count($groups) . ' 个装备分组');

        // 收集每个分组下的典型type_id来推断分类
        $groupCategories = [];
        
        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            
            // 获取该分组下的一些type_id
            $types = $pdo->query("
                SELECT type_id FROM fitting_types 
                WHERE group_id = {$groupId} AND published = 1 
                LIMIT 5
            ")->fetchAll(PDO::FETCH_COLUMN);

            $categoryNames = [];
            $cnNames = [];
            
            foreach ($types as $typeId) {
                $typeIdStr = (string)$typeId;
                if (isset($localItems[$typeIdStr])) {
                    $item = $localItems[$typeIdStr];
                    $cat = $item['category'] ?? [];
                    
                    // 获取二级分类（舰船装备下的子分类）
                    if (count($cat) >= 2 && $cat[0] === '舰船装备') {
                        $categoryNames[] = $cat[1] ?? '';
                    } elseif (count($cat) >= 2 && $cat[0] === '无人机') {
                        $categoryNames[] = '无人机';
                    } elseif (count($cat) >= 1 && in_array($cat[0], ['改装件', '工程改装件', '护盾改装件', '装甲改装件', '武器改装件', '电子改装件', '无人机改装件', '旗舰改装件', '采矿改装件', '导航改装件'])) {
                        $categoryNames[] = '改装件';
                    }
                    
                    // 收集中文名作为分组名参考
                    $cnNames[] = $item['name'] ?? '';
                }
            }
            
            // 统计最常见的分类
            if (!empty($categoryNames)) {
                $counts = array_count_values($categoryNames);
                arsort($counts);
                $mainCategory = array_key_first($counts);
                $groupCategories[$groupId] = [
                    'category' => $mainCategory,
                    'cn_name' => !empty($cnNames) ? $this->inferGroupCnName($groupId, $cnNames) : $group['name_en'],
                    'slot' => $this->MODULE_CATEGORY_MAP[$mainCategory] ?? null,
                ];
            }
        }

        // 显示并更新分组
        $updated = 0;
        $this->newLine();
        $this->info('=== 装备分组翻译 ===');
        
        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            $oldName = $group['name_en'];
            $oldNameCn = $group['name_cn'];
            
            if (isset($groupCategories[$groupId])) {
                $info = $groupCategories[$groupId];
                $newNameCn = $info['cn_name'];
                $category = $info['category'];
                $slot = $info['slot'] ?? '未知';
                
                if ($newNameCn && $newNameCn !== $oldNameCn) {
                    $this->line("group_id {$groupId}: {$oldName} -> {$newNameCn} ({$category}/{$slot})");
                    
                    if (!$dryRun) {
                        $stmt = $pdo->prepare("UPDATE fitting_groups SET name_cn = ? WHERE group_id = ?");
                        $stmt->execute([$newNameCn, $groupId]);
                        $updated++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info("已更新: {$updated} 个分组");

        // 清除缓存
        if (!$dryRun && $updated > 0) {
            Cache::forget('fitting_module_categories');
            $this->info('已清除缓存');
        }

        return 0;
    }

    /**
     * 根据分组ID和物品名推断分组中文名
     */
    private function inferGroupCnName(int $groupId, array $cnNames): string
    {
        // 已知的分组ID到中文名映射
        $knownMappings = [
            // 护盾相关
            38 => '护盾扩展装置',
            39 => '护盾回充器',
            40 => '护盾增强器',
            41 => '远程护盾传输装置',
            77 => '护盾增强系统',
            295 => '护盾抗性放大器',
            338 => '护盾增效放大器',
            770 => '护盾通量线圈',
            57 => '护盾能量继电器',
            1156 => '辅助护盾增强器',
            
            // 装甲相关
            62 => '装甲维修器',
            328 => '装甲增强器',
            329 => '装甲板',
            98 => '装甲镀层',
            326 => '装甲附甲板',
            1150 => '装甲抗性增强器',
            1199 => '辅助装甲维修器',
            1699 => '灵活装甲增强器',
            325 => '远程装甲维修器',
            1698 => '辅助远程装甲维修器',
            
            // 电子战
            80 => '脉冲干扰器',
            201 => 'ECM',
            202 => 'ECM增效器',
            203 => 'ECCM',
            514 => 'ECM稳定器',
            208 => '感应器干扰器',
            212 => '感应器增效器',
            210 => '信号放大器',
            213 => '追踪计算机',
            211 => '追踪增效器',
            379 => '目标标记器',
            842 => '爆发投射器',
            1672 => '停滞缠绕光束',
            65 => '停滞缠绕',
            
            // 武器
            53 => '能量武器',
            74 => '混合武器',
            55 => '射弹武器',
            506 => '巡航导弹发射器',
            507 => '鱼雷发射器',
            508 => '鱼雷发射器',
            509 => '轻型导弹发射器',
            510 => '重型导弹发射器',
            511 => '快速轻型导弹发射器',
            512 => '防御导弹发射器',
            771 => '重型突击导弹发射器',
            1245 => '快速重型导弹发射器',
            1673 => '快速鱼雷发射器',
            862 => '炸弹发射器',
            72 => '立体炸弹',
            588 => '超级武器',
            
            // 推进
            46 => '推进器',
            54 => '采矿激光器',
            483 => '频率采矿激光器',
            464 => '采集激光器',
            
            // 无人机
            100 => '战斗无人机',
            639 => '电子战无人机',
            640 => '后勤无人机',
            101 => '采矿无人机',
            1159 => '打捞无人机',
            641 => '停滞缠绕无人机',
            544 => '能量中和无人机',
            
            // 能量
            43 => '电容回充器',
            61 => '电容电池',
            76 => '电容注电器',
            67 => '远程电容传输装置',
            767 => '电容能量继电器',
            768 => '电容通量线圈',
            
            // CPU/电网
            285 => 'CPU增效器',
            339 => '辅助动力核心',
            769 => '反应堆控制单元',
            766 => '能源诊断系统',
            
            // 跃迁
            52 => '跃迁干扰器',
            899 => '跃迁干扰力场发生器',
            315 => '跃迁核心稳定器',
            
            // 损伤控制
            60 => '损伤控制',
            78 => '加固舱壁',
            
            // 其他
            47 => '货柜扫描器',
            48 => '舰船扫描器',
            49 => '测量扫描器',
            330 => '隐形装置',
            658 => '诱导力场发生器',
            4174 => '压缩机',
            1770 => '指挥突发',
            538 => '数据采集器',
            481 => '扫描探针发射器',
            1122 => '打捞器',
            650 => '牵引光束',
            96 => '自动目标系统',
            515 => '会战模块',
            1154 => '信号抑制器',
            
            // 改装件
            773 => '装甲改装件',
            774 => '护盾改装件',
            775 => '能量武器改装件',
            776 => '混合武器改装件',
            777 => '射弹武器改装件',
            779 => '导弹改装件',
            778 => '无人机改装件',
            781 => '工程改装件',
            782 => '导航改装件',
            786 => '电子改装件',
            1232 => '资源处理改装件',
            1233 => '扫描改装件',
            1234 => '瞄准改装件',
            1308 => '锚定改装件',
            
            // 武器升级
            59 => '回转稳定器',
            205 => '散热器',
            302 => '磁性力场稳定器',
            367 => '弹道控制系统',
            1988 => '熵辐射增波器',
            546 => '采矿升级',
            645 => '无人机损伤升级',
            1292 => '无人机追踪增效器',
            1395 => '导弹制导增强器',
            
            // 船体升级
            762 => '惯性稳定器',
            763 => '纳米纤维内部结构',
            764 => '超载喷射系统',
            765 => '扩展货柜',
        ];
        
        return $knownMappings[$groupId] ?? ($cnNames[0] ?? '');
    }

    /**
     * 加载本地JSON
     */
    private function loadLocalJson(): array
    {
        if (!file_exists(self::ITEMS_JSON)) {
            return [];
        }
        $content = file_get_contents(self::ITEMS_JSON);
        return json_decode($content, true) ?? [];
    }
}