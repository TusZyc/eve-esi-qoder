<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PDO;

class ImportFittingSde extends Command
{
    protected $signature = 'fitting:import-sde 
        {--force : 强制重新导入，即使数据库已存在}
        {--file= : 使用本地 SDE SQLite 文件路径（跳过下载）}
        {--skip-download : 跳过下载步骤，使用已有的 sqlite-latest.sqlite}';

    protected $description = '从 Fuzzwork SDE 导入装配相关数据到 fitting.sqlite';

    // 需要提取的物品分类
    private const SHIP_CATEGORIES = [6];           // 舰船
    private const MODULE_CATEGORIES = [7];         // 装备
    private const CHARGE_CATEGORIES = [8];         // 弹药/电荷
    private const DRONE_CATEGORIES = [18];         // 无人机
    private const SKILL_CATEGORIES = [16];         // 技能
    private const IMPLANT_CATEGORIES = [24];       // 植入体
    private const SUBSYSTEM_CATEGORIES = [32];     // 子系统

    // 装备槽位属性 ID
    private const SLOT_ATTR_IDS = [
        12 => 'lowSlots',      // 低槽数
        13 => 'medSlots',      // 中槽数  
        14 => 'hiSlots',       // 高槽数
        1137 => 'rigSlots',    // 改装件槽数
        102 => 'turretSlotsLeft',   // 炮台硬点
        101 => 'launcherSlotsLeft', // 发射器硬点
    ];

    // 装备效果 ID（用于判断槽位类型）
    private const EFFECT_SLOT_MAP = [
        10 => 'low',   // 低槽效果
        11 => 'med',   // 中槽效果
        12 => 'high',  // 高槽效果
        2663 => 'rig', // 改装件效果
        3772 => 'subsystem', // 子系统效果
    ];

    // 关键属性 ID 列表（装配计算所需）
    private const KEY_ATTR_IDS = [
        // 舰船资源输出
        48, 11, 482, 55, 1132,
        // 装备资源消耗
        50, 30, 1153,
        // 槽位数量
        12, 13, 14, 1137, 102, 101,
        // HP 与抗性
        263, 265, 9, 271, 272, 273, 274, 267, 268, 269, 270,
        // 机动性
        37, 70, 552, 600,
        // 锁定
        76, 564, 192,
        // 无人机
        1271, 283,
        // 其他重要属性
        38, 54, 208, 209, 210, 211, 212, 213, 214, 215, 216, 217, 218, 219, 220,
        // 改装件相关
        1544, 1545, 1546, 1547, 1548, 1549, 1550, 1551, 1552, 1553, 1554, 1555,
        // 效果属性
        633, 634, 635, 636, 637, 638, 639, 640,
    ];

    public function handle()
    {
        set_time_limit(600);
        
        $force = $this->option('force');
        $localFile = $this->option('file');
        $skipDownload = $this->option('skip-download');

        $fittingDbPath = database_path('fitting.sqlite');
        
        // 检查数据库是否已存在
        if (file_exists($fittingDbPath) && !$force) {
            $this->info('fitting.sqlite 已存在。使用 --force 强制重新导入。');
            return 0;
        }

        $this->info('=== 开始导入 SDE 装配数据 ===');

        // 步骤 1: 获取 SDE SQLite 文件
        $sdePath = $this->getSdeFile($localFile, $skipDownload);
        if (!$sdePath) {
            return 1;
        }

        // 步骤 2: 创建 fitting.sqlite 结构
        $this->createFittingSchema($fittingDbPath);

        // 步骤 3: 从 SDE 提取数据
        $this->importDataFromSde($sdePath, $fittingDbPath);

        // 步骤 4: 处理效果 modifiers
        $this->processEffectModifiers($fittingDbPath);

        // 清理临时文件
        if (!$localFile && !$skipDownload && file_exists(storage_path('app/sde/sqlite-latest.sqlite'))) {
            $this->info('保留下载的 SDE 文件供后续使用。');
        }

        $this->info('=== 导入完成 ===');
        $this->showStats($fittingDbPath);

        return 0;
    }

    /**
     * 获取 SDE SQLite 文件
     */
    private function getSdeFile(?string $localFile, bool $skipDownload): ?string
    {
        if ($localFile && file_exists($localFile)) {
            $this->info("使用本地 SDE 文件: {$localFile}");
            return $localFile;
        }

        $storedPath = storage_path('app/sde/sqlite-latest.sqlite');
        if ($skipDownload && file_exists($storedPath)) {
            $this->info("使用已存储的 SDE 文件: {$storedPath}");
            return $storedPath;
        }

        // 下载 SDE
        $this->info('下载 Fuzzwork SQLite SDE...');
        $sdeUrl = 'https://www.fuzzwork.co.uk/dump/sqlite-latest.sqlite.bz2';
        
        $bz2Path = storage_path('app/sde/sqlite-latest.sqlite.bz2');
        
        // 创建目录
        if (!is_dir(storage_path('app/sde'))) {
            mkdir(storage_path('app/sde'), 0755, true);
        }

        try {
            $this->info("下载: {$sdeUrl}");
            $response = Http::timeout(300)
                ->withOptions(['sink' => $bz2Path])
                ->get($sdeUrl);

            if (!$response->successful()) {
                $this->error('下载失败: ' . $response->status());
                return null;
            }

            $this->info('解压 bz2 文件...');
            // 使用命令行解压（Windows 可能需要额外工具）
            if (function_exists('shell_exec')) {
                // 尝试使用 bzip2 或 7z
                if (shell_exec('bzip2 -d ' . escapeshellarg($bz2Path) . ' 2>&1')) {
                    $this->info('使用 bzip2 解压成功');
                } elseif (shell_exec('7z x ' . escapeshellarg($bz2Path) . ' -o' . escapeshellarg(storage_path('app/sde')) . ' 2>&1')) {
                    $this->info('使用 7z 解压成功');
                } else {
                    // PHP 方式解压
                    $this->decompressBz2($bz2Path, $storedPath);
                }
            } else {
                $this->decompressBz2($bz2Path, $storedPath);
            }

            return $storedPath;

        } catch (\Exception $e) {
            $this->error('下载或解压失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * PHP 方式解压 bz2
     */
    private function decompressBz2(string $bz2Path, string $outPath): void
    {
        $this->info('使用 PHP 内置函数解压...');
        
        $input = bzopen($bz2Path, 'r');
        $output = fopen($outPath, 'wb');
        
        while (!feof($input)) {
            $chunk = bzread($input, 4096);
            if ($chunk === false) {
                break;
            }
            fwrite($output, $chunk);
        }
        
        bzclose($input);
        fclose($output);
        
        unlink($bz2Path);
        $this->info('解压完成: ' . round(filesize($outPath) / 1024 / 1024, 2) . ' MB');
    }

    /**
     * 创建 fitting.sqlite 数据库结构
     */
    private function createFittingSchema(string $dbPath): void
    {
        $this->info('创建 fitting.sqlite 数据库结构...');
        
        // 删除旧文件
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 创建表结构
        $schema = "
            -- 物品基础信息
            CREATE TABLE fitting_types (
                type_id INTEGER PRIMARY KEY,
                group_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                name_en TEXT NOT NULL,
                name_cn TEXT,
                mass REAL,
                volume REAL,
                capacity REAL,
                description TEXT,
                published INTEGER DEFAULT 1,
                market_group_id INTEGER,
                meta_level INTEGER
            );

            -- 物品属性值
            CREATE TABLE fitting_attributes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type_id INTEGER NOT NULL,
                attribute_id INTEGER NOT NULL,
                value_int INTEGER,
                value_float REAL,
                UNIQUE(type_id, attribute_id)
            );
            CREATE INDEX idx_attr_type ON fitting_attributes(type_id);
            CREATE INDEX idx_attr_attr ON fitting_attributes(attribute_id);

            -- 效果定义
            CREATE TABLE fitting_effects (
                effect_id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                effect_category INTEGER DEFAULT 0,
                is_assistance INTEGER DEFAULT 0,
                is_offensive INTEGER DEFAULT 0,
                discharge_attribute_id INTEGER,
                duration_attribute_id INTEGER,
                range_attribute_id INTEGER,
                modifiers_json TEXT
            );

            -- 物品-效果映射
            CREATE TABLE fitting_type_effects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type_id INTEGER NOT NULL,
                effect_id INTEGER NOT NULL,
                is_default INTEGER DEFAULT 1,
                UNIQUE(type_id, effect_id)
            );
            CREATE INDEX idx_effect_type ON fitting_type_effects(type_id);

            -- 属性定义
            CREATE TABLE fitting_attribute_types (
                attribute_id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                unit_id INTEGER,
                category_id INTEGER,
                stackable INTEGER DEFAULT 1,
                high_is_good INTEGER DEFAULT 1
            );

            -- 物品组（用于分类显示）
            CREATE TABLE fitting_groups (
                group_id INTEGER PRIMARY KEY,
                category_id INTEGER NOT NULL,
                name_en TEXT NOT NULL,
                name_cn TEXT,
                published INTEGER DEFAULT 1
            );

            -- 物品分类
            CREATE TABLE fitting_categories (
                category_id INTEGER PRIMARY KEY,
                name_en TEXT NOT NULL,
                name_cn TEXT,
                published INTEGER DEFAULT 1
            );

            -- 科技等级（meta type）
            CREATE TABLE fitting_meta_types (
                type_id INTEGER PRIMARY KEY,
                meta_type_id INTEGER NOT NULL,
                parent_type_id INTEGER
            );
        ";

        $pdo->exec($schema);
        $this->info('数据库结构创建完成');
    }

    /**
     * 从 SDE 导入数据
     */
    private function importDataFromSde(string $sdePath, string $fittingPath): void
    {
        $this->info('连接 SDE 数据库...');
        
        $sdePdo = new PDO('sqlite:' . $sdePath);
        $sdePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $fitPdo = new PDO('sqlite:' . $fittingPath);
        $fitPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取所有需要的分类 ID
        $categoryIds = array_merge(
            self::SHIP_CATEGORIES,
            self::MODULE_CATEGORIES,
            self::CHARGE_CATEGORIES,
            self::DRONE_CATEGORIES,
            self::SKILL_CATEGORIES,
            self::IMPLANT_CATEGORIES,
            self::SUBSYSTEM_CATEGORIES
        );

        // 1. 导入分类
        $this->importCategories($sdePdo, $fitPdo);

        // 2. 导入组
        $this->importGroups($sdePdo, $fitPdo, $categoryIds);

        // 3. 导入物品
        $this->importTypes($sdePdo, $fitPdo, $categoryIds);

        // 4. 导入属性值
        $this->importTypeAttributes($sdePdo, $fitPdo);

        // 5. 导入效果
        $this->importEffects($sdePdo, $fitPdo);

        // 6. 导入物品-效果映射
        $this->importTypeEffects($sdePdo, $fitPdo);

        // 7. 导入属性定义
        $this->importAttributeTypes($sdePdo, $fitPdo);

        // 8. 导入科技等级
        $this->importMetaTypes($sdePdo, $fitPdo);
    }

    /**
     * 导入分类
     */
    private function importCategories(PDO $sde, PDO $fit): void
    {
        $this->info('导入物品分类...');
        
        $stmt = $sde->query("SELECT categoryID, categoryName, published FROM invCategories WHERE published = 1");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fit->beginTransaction();
        $insert = $fit->prepare("INSERT INTO fitting_categories (category_id, name_en, published) VALUES (?, ?, ?)");
        
        foreach ($rows as $row) {
            $insert->execute([$row['categoryID'], $row['categoryName'] ?? '', $row['published'] ?? 1]);
        }
        
        $fit->commit();
        $this->info("导入 " . count($rows) . " 个分类");
    }

    /**
     * 导入组
     */
    private function importGroups(PDO $sde, PDO $fit, array $categoryIds): void
    {
        $this->info('导入物品组...');
        
        $catList = implode(',', $categoryIds);
        $stmt = $sde->query("SELECT groupID, categoryID, groupName, published FROM invGroups WHERE categoryID IN ($catList) AND published = 1");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fit->beginTransaction();
        $insert = $fit->prepare("INSERT INTO fitting_groups (group_id, category_id, name_en, published) VALUES (?, ?, ?, ?)");
        
        foreach ($rows as $row) {
            $insert->execute([$row['groupID'], $row['categoryID'], $row['groupName'], $row['published']]);
        }
        
        $fit->commit();
        $this->info("导入 " . count($rows) . " 个组");
    }

    /**
     * 导入物品
     */
    private function importTypes(PDO $sde, PDO $fit, array $categoryIds): void
    {
        $this->info('导入物品...');
            
        $catList = implode(',', $categoryIds);
        $stmt = $sde->query(
            "SELECT t.typeID, t.groupID, g.categoryID, t.typeName, t.mass, t.volume, 
                   t.capacity, t.description, t.published, t.marketGroupID
            FROM invTypes t
            JOIN invGroups g ON t.groupID = g.groupID
            WHERE g.categoryID IN ($catList)"
        );

        $fit->beginTransaction();
        $insert = $fit->prepare(
            "INSERT INTO fitting_types (type_id, group_id, category_id, name_en, mass, volume, capacity, description, published, market_group_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
    
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute([
                $row['typeID'],
                $row['groupID'],
                $row['categoryID'],
                $row['typeName'],
                $row['mass'],
                $row['volume'],
                $row['capacity'],
                $row['description'],
                $row['published'] ?? 1,
                $row['marketGroupID']
            ]);
            $count++;
        }
            
        $fit->commit();
        $this->info("导入 {$count} 个物品");
    }

    /**
     * 导入物品属性值
     */
    private function importTypeAttributes(PDO $sde, PDO $fit): void
    {
        $this->info('导入物品属性值...');
            
        // 获取 fitting_types 中的所有 type_id
        $typeIds = $fit->query("SELECT type_id FROM fitting_types")->fetchAll(PDO::FETCH_COLUMN);
            
        $typeIdList = implode(',', $typeIds);
        
        $stmt = $sde->query(
            "SELECT typeID, attributeID, valueInt, valueFloat 
            FROM dgmTypeAttributes 
            WHERE typeID IN ($typeIdList)"
        );

        $fit->beginTransaction();
        $insert = $fit->prepare(
            "INSERT INTO fitting_attributes (type_id, attribute_id, value_int, value_float)
            VALUES (?, ?, ?, ?)" 
        );
    
        $count = 0;
        $batch = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute([
                $row['typeID'],
                $row['attributeID'],
                $row['valueInt'],
                $row['valueFloat']
            ]);
            $count++;
            $batch++;
                
            if ($batch >= 10000) {
                $fit->commit();
                $fit->beginTransaction();
                $batch = 0;
            }
        }
            
        $fit->commit();
        $this->info("导入 {$count} 条属性记录");
    }

    /**
     * 导入效果定义
     */
    private function importEffects(PDO $sde, PDO $fit): void
    {
        $this->info('导入效果定义...');
        
        $stmt = $sde->query(
            "SELECT effectID, effectName, description, effectCategory, 
                   isAssistance, isOffensive, dischargeAttributeID,
                   durationAttributeID, rangeAttributeID, modifierInfo
            FROM dgmEffects"
        );

        $fit->beginTransaction();
        $insert = $fit->prepare(
            "INSERT INTO fitting_effects (effect_id, name, description, effect_category, 
                                         is_assistance, is_offensive, discharge_attribute_id,
                                         duration_attribute_id, range_attribute_id, modifiers_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" 
        );
    
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // 将 YAML modifierInfo 转为 JSON
            $modifiersJson = null;
            if ($row['modifierInfo']) {
                try {
                    // 尝试解析 YAML（需要 yaml 扩展）
                    if (function_exists('yaml_parse')) {
                        $modifiers = yaml_parse($row['modifierInfo']);
                        $modifiersJson = json_encode($modifiers, JSON_UNESCAPED_UNICODE);
                    } else {
                        // 简单处理：直接存储 YAML，后续处理
                        $modifiersJson = json_encode(['yaml' => $row['modifierInfo']]);
                    }
                } catch (\Exception $e) {
                    $modifiersJson = json_encode(['raw' => $row['modifierInfo']]);
                }
            }
    
            $insert->execute([
                $row['effectID'],
                $row['effectName'],
                $row['description'],
                $row['effectCategory'] ?? 0,
                $row['isAssistance'] ?? 0,
                $row['isOffensive'] ?? 0,
                $row['dischargeAttributeID'],
                $row['durationAttributeID'],
                $row['rangeAttributeID'],
                $modifiersJson
            ]);
            $count++;
        }
        
        $fit->commit();
        $this->info("导入 {$count} 个效果");
    }

    /**
     * 导入物品-效果映射
     */
    private function importTypeEffects(PDO $sde, PDO $fit): void
    {
        $this->info('导入物品-效果映射...');
        
        $typeIds = $fit->query("SELECT type_id FROM fitting_types")->fetchAll(PDO::FETCH_COLUMN);
        $typeIdList = implode(',', $typeIds);
        
        $stmt = $sde->query("
            SELECT typeID, effectID, isDefault
            FROM dgmTypeEffects 
            WHERE typeID IN ($typeIdList)
        ");

        $fit->beginTransaction();
        $insert = $fit->prepare("
            INSERT INTO fitting_type_effects (type_id, effect_id, is_default)
            VALUES (?, ?, ?)
        ");

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute([
                $row['typeID'],
                $row['effectID'],
                $row['isDefault'] ?? 1
            ]);
            $count++;
        }
        
        $fit->commit();
        $this->info("导入 {$count} 条效果映射");
    }

    /**
     * 导入属性定义
     */
    private function importAttributeTypes(PDO $sde, PDO $fit): void
    {
        $this->info('导入属性定义...');
        
        $stmt = $sde->query("
            SELECT attributeID, attributeName, description, unitID, categoryID, stackable, highIsGood
            FROM dgmAttributeTypes
        ");

        $fit->beginTransaction();
        $insert = $fit->prepare("
            INSERT INTO fitting_attribute_types (attribute_id, name, description, unit_id, category_id, stackable, high_is_good)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute([
                $row['attributeID'],
                $row['attributeName'] ?? 'attr_' . $row['attributeID'],
                $row['description'],
                $row['unitID'],
                $row['categoryID'],
                $row['stackable'] ?? 1,
                $row['highIsGood'] ?? 1
            ]);
            $count++;
        }
        
        $fit->commit();
        $this->info("导入 {$count} 个属性定义");
    }

    /**
     * 导入科技等级
     */
    private function importMetaTypes(PDO $sde, PDO $fit): void
    {
        $this->info('导入科技等级...');
        
        $typeIds = $fit->query("SELECT type_id FROM fitting_types")->fetchAll(PDO::FETCH_COLUMN);
        $typeIdList = implode(',', $typeIds);
        
        $stmt = $sde->query("
            SELECT typeID, metaGroupID, parentTypeID
            FROM invMetaTypes
            WHERE typeID IN ($typeIdList)
        ");

        $fit->beginTransaction();
        $insert = $fit->prepare("
            INSERT INTO fitting_meta_types (type_id, meta_type_id, parent_type_id)
            VALUES (?, ?, ?)
        ");

        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert->execute([
                $row['typeID'],
                $row['metaGroupID'],
                $row['parentTypeID']
            ]);
            $count++;
        }
        
        $fit->commit();
        $this->info("导入 {$count} 条科技等级记录");
    }

    /**
     * 处理效果 modifiers（解析 YAML）
     */
    private function processEffectModifiers(string $dbPath): void
    {
        $this->info('处理效果 modifiers...');
        
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->query("SELECT effect_id, modifiers_json FROM fitting_effects WHERE modifiers_json IS NOT NULL");
        
        $update = $pdo->prepare("UPDATE fitting_effects SET modifiers_json = ? WHERE effect_id = ?");
        
        $processed = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $modifiersJson = $row['modifiers_json'];
            $data = json_decode($modifiersJson, true);
            
            if (isset($data['yaml'])) {
                // 解析 YAML 格式的 modifier_info
                $parsed = $this->parseModifierYaml($data['yaml']);
                $update->execute([json_encode($parsed, JSON_UNESCAPED_UNICODE), $row['effect_id']]);
                $processed++;
            }
        }
        
        $this->info("处理 {$processed} 个效果的 modifiers");
    }

    /**
     * 解析 YAML 格式的 modifier_info
     */
    private function parseModifierYaml(string $yaml): array
    {
        // 简单 YAML 解析（针对 modifier_info 特定格式）
        // 格式通常是：
        // modifierInfo:
        // - domain: shipID
        //   func: ItemModifier
        //   modifiedAttributeID: 76
        //   modifyingAttributeID: 1991
        //   operator: 5
        
        $result = [];
        $lines = explode("\n", $yaml);
        $currentModifier = null;
        $inModifierInfo = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if ($trimmed === 'modifierInfo:') {
                $inModifierInfo = true;
                continue;
            }
            
            if (!$inModifierInfo) continue;
            
            // 检测新 modifier 开始（以 - 开头）
            if (substr($trimmed, 0, 2) === '- ') {
                if ($currentModifier) {
                    $result[] = $currentModifier;
                }
                $currentModifier = [];
                $trimmed = trim(substr($trimmed, 2));
            }
            
            if (!$currentModifier) continue;
            
            // 解析键值对
            if (strpos($trimmed, ':') !== false) {
                $parts = explode(':', $trimmed, 2);
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // 尝试转换为数字
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
                }
                
                $currentModifier[$key] = $value;
            }
        }
        
        if ($currentModifier) {
            $result[] = $currentModifier;
        }
        
        return $result;
    }

    /**
     * 显示导入统计
     */
    private function showStats(string $dbPath): void
    {
        $pdo = new PDO('sqlite:' . $dbPath);

        $stats = [
            'fitting_types' => '物品',
            'fitting_attributes' => '属性值',
            'fitting_effects' => '效果',
            'fitting_type_effects' => '效果映射',
            'fitting_attribute_types' => '属性定义',
            'fitting_groups' => '物品组',
            'fitting_categories' => '分类',
            'fitting_meta_types' => '科技等级',
        ];

        $this->newLine();
        $this->info('=== 数据统计 ===');
        foreach ($stats as $table => $name) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $this->info("{$name}: {$count} 条");
        }

        $this->info("数据库大小: " . round(filesize($dbPath) / 1024 / 1024, 2) . " MB");
    }
}