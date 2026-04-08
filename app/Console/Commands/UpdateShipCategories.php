<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use PDO;

/**
 * 更新舰船分类翻译和配置
 * 
 * 根据本地 eve_items.json 数据：
 * 1. 翻译 fitting_groups 表的 name_cn 字段
 * 2. 生成正确的舰船分类配置（替换 FittingDataService 中的硬编码）
 */
class UpdateShipCategories extends Command
{
    protected $signature = 'fitting:update-categories
        {--json= : 使用本地 eve_items.json 文件路径}
        {--dry-run : 只显示将要执行的更改，不实际修改数据库}';

    protected $description = '更新舰船分类翻译和配置';

    // SDE group_id 到中文翻译映射（根据 eve_items.json 的分类结构）
    private const GROUP_TRANSLATIONS = [
        // 护卫舰系列
        25 => '护卫舰',           // Frigate - 标准/势力护卫舰
        237 => '轻型护卫舰',      // Corvette
        324 => '突击护卫舰',      // Assault Frigate
        830 => '隐形特勤舰',      // Covert Ops
        831 => '拦截舰',          // Interceptor
        834 => '隐形轰炸机',      // Stealth Bomber
        883 => '旗舰工业舰',      // Capital Industrial Ship (如 Rorqual)
        893 => '电子攻击舰',      // Electronic Attack Ship
        1283 => '远征护卫舰',     // Expedition Frigate
        1527 => '后勤护卫舰',     // Logistics Frigate
        
        // 驱逐舰系列
        420 => '驱逐舰',          // Destroyer
        541 => '拦截舰',          // Interdictor
        1305 => '战术驱逐舰',     // Tactical Destroyer
        1534 => '指挥驱逐舰',     // Command Destroyer
        
        // 巡洋舰系列
        26 => '巡洋舰',           // Cruiser
        358 => '重型突击巡洋舰',  // Heavy Assault Cruiser
        832 => '后勤舰',          // Logistics
        833 => '侦察舰',          // Force Recon Ship
        894 => '重型拦截巡洋舰',  // Heavy Interdiction Cruiser
        906 => '侦察舰',          // Combat Recon Ship
        963 => '战略巡洋舰',      // Strategic Cruiser
        1972 => '旗舰巡洋舰',     // Flag Cruiser
        
        // 战列巡洋舰系列
        419 => '战列巡洋舰',      // Combat Battlecruiser
        540 => '指挥舰',          // Command Ship
        1201 => '战列巡洋舰',     // Attack Battlecruiser
        
        // 战列舰系列
        27 => '战列舰',           // Battleship
        898 => '黑隐特勤舰',      // Black Ops
        900 => '掠夺舰',          // Marauder
        
        // 旗舰系列
        30 => '泰坦',             // Titan
        485 => '无畏舰',          // Dreadnought
        4594 => '长矛无畏舰',     // Lancer Dreadnought
        547 => '航母',            // Carrier
        659 => '超级航母',        // Supercarrier
        1538 => '战力辅助舰',     // Force Auxiliary
        
        // 工业舰系列
        28 => '运载舰',           // Industrial / Hauler
        29 => '蛋舱',             // Capsule
        31 => '穿梭机',           // Shuttle
        380 => '运输舰',          // Deep Space Transport
        513 => '货舰',            // Freighter
        902 => '跳跃货舰',        // Jump Freighter
        1202 => '运输舰',         // Blockade Runner
        941 => '工业指挥舰',      // Industrial Command Ship
        
        // 采矿系列
        463 => '采矿驳船',        // Mining Barge
        543 => '掘矿者',          // Exhumer
        
        // 特殊
        1022 => '特别版舰船',     // Prototype Exploration Ship
    ];

    // 正确的舰船分类配置（group_id 按舰船类型分组）
    private const SHIP_CATEGORIES = [
        'frigate' => [
            'name' => '护卫舰',
            'group_ids' => [25, 324, 830, 831, 834, 893, 1283, 1527],
        ],
        'corvette' => [
            'name' => '轻型护卫舰',
            'group_ids' => [237],
        ],
        'destroyer' => [
            'name' => '驱逐舰',
            'group_ids' => [420, 541, 1305, 1534],
        ],
        'cruiser' => [
            'name' => '巡洋舰',
            'group_ids' => [26, 358, 832, 833, 894, 906, 963, 1972],
        ],
        'battlecruiser' => [
            'name' => '战列巡洋舰',
            'group_ids' => [419, 540, 1201],
        ],
        'battleship' => [
            'name' => '战列舰',
            'group_ids' => [27, 898, 900],
        ],
        'capital' => [
            'name' => '旗舰',
            'group_ids' => [30, 485, 4594, 547, 659, 1538],
        ],
        'industrial' => [
            'name' => '工业舰',
            'group_ids' => [28, 380, 513, 902, 1202, 941, 883],
        ],
        'mining' => [
            'name' => '采矿舰',
            'group_ids' => [463, 543],
        ],
        'shuttle' => [
            'name' => '穿梭机',
            'group_ids' => [29, 31],
        ],
        'special' => [
            'name' => '特别版',
            'group_ids' => [1022],
        ],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $jsonPath = $this->option('json') ?: '/var/www/html/data/eve_items.json';

        $this->info('=== 更新舰船分类翻译 ===');

        // 检查数据库连接
        $pdo = DB::connection('fitting')->getPdo();
        
        // 1. 翻译 fitting_groups 表
        $this->updateGroupTranslations($pdo, $dryRun);

        // 2. 翻译 fitting_categories 表
        $this->updateCategoryTranslations($pdo, $dryRun);

        // 3. 清除缓存
        if (!$dryRun) {
            Cache::forget('fitting_ship_categories');
            Cache::forget('fitting_ship_tree');
            $this->info('已清除缓存');
        }

        // 4. 显示分类配置（供 FittingDataService 使用）
        $this->displayCategoryConfig();

        $this->info('=== 完成 ===');
        return 0;
    }

    /**
     * 翻译 fitting_groups 表
     */
    private function updateGroupTranslations(PDO $pdo, bool $dryRun): void
    {
        $this->info('更新舰船组翻译...');

        // 获取舰船相关的组
        $groups = $pdo->query(
            "SELECT group_id, name_en, name_cn FROM fitting_groups WHERE category_id = 6"
        )->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            $nameEn = $group['name_en'];
            $nameCn = $group['name_cn'];

            // 查找翻译
            $translation = self::GROUP_TRANSLATIONS[$groupId] ?? null;

            if ($translation && (!$nameCn || $nameCn === $nameEn)) {
                $this->line("  {$groupId}: {$nameEn} → {$translation}");
                
                if (!$dryRun) {
                    $pdo->prepare("UPDATE fitting_groups SET name_cn = ? WHERE group_id = ?")
                        ->execute([$translation, $groupId]);
                }
                $updated++;
            } elseif ($nameCn && $nameCn !== $nameEn) {
                $this->line("  {$groupId}: {$nameEn} 已有翻译 {$nameCn} (跳过)");
            } else {
                $this->line("  {$groupId}: {$nameEn} 无翻译映射 (保留英文)");
            }
        }

        $this->info("更新 {$updated} 个舰船组翻译");
    }

    /**
     * 翻译 fitting_categories 表
     */
    private function updateCategoryTranslations(PDO $pdo, bool $dryRun): void
    {
        $this->info('更新分类翻译...');

        $categoryTranslations = [
            6 => '舰船',
            7 => '装备',
            8 => '弹药',
            18 => '无人机',
            16 => '技能',
            24 => '植入体',
            32 => '子系统',
        ];

        foreach ($categoryTranslations as $catId => $nameCn) {
            $cat = $pdo->query(
                "SELECT name_en, name_cn FROM fitting_categories WHERE category_id = {$catId}"
            )->fetch(PDO::FETCH_ASSOC);

            if ($cat) {
                $this->line("  {$catId}: {$cat['name_en']} → {$nameCn}");
                
                if (!$dryRun) {
                    $pdo->prepare("UPDATE fitting_categories SET name_cn = ? WHERE category_id = ?")
                        ->execute([$nameCn, $catId]);
                }
            }
        }
    }

    /**
     * 显示分类配置（用于更新 FittingDataService）
     */
    private function displayCategoryConfig(): void
    {
        $this->newLine();
        $this->info('=== 正确的分类配置 ===');
        $this->info('将以下配置替换 FittingDataService::getShipCategories() 中的硬编码：');
        $this->newLine();

        foreach (self::SHIP_CATEGORIES as $key => $cat) {
            $this->line("'{$key}' => ['name' => '{$cat['name']}', 'group_ids' => [" . implode(', ', $cat['group_ids']) . "]],");
        }
    }
}