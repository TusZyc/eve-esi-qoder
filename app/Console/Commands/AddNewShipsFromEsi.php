<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use PDO;

/**
 * 从国服 ESI 添加新舰船到数据库
 * 
 * 用于添加 SDE 中不存在的新版本舰船（如特别版舰船）
 */
class AddNewShipsFromEsi extends Command
{
    protected $signature = 'fitting:add-new-ships
        {--ids= : 指定要添加的舰船ID，逗号分隔}
        {--dry-run : 只显示将要添加的舰船，不实际添加}';

    protected $description = '从国服ESI获取新舰船数据并添加到数据库';

    private const ESI_BASE = 'https://esi.evepc.163.com';
    private const ITEMS_JSON = '/var/www/html/data/eve_items.json';

    // 已知的新舰船ID（SDE中不存在但eve_items.json中存在的）
    private const NEW_SHIP_IDS = [
        // 巨像系列
        78589, 84925, 86485, 89107,
        // 特别版侦察舰
        81386, 82192, 84004, 84373,
        // 特别版突击护卫舰
        84219,
        // 特别版掠夺舰
        77500, 80983,
        // 特别版航空母舰
        76009,
        // 特别版重型拦截巡洋舰
        77980,
        // 特别版战列巡洋舰
        89807,
        // 特别版驱逐舰
        89808, 91174,
    ];

    // group_id 到 category_id 的映射（舰船都是 6）
    private const SHIP_CATEGORY_ID = 6;

    // 英文组名到 group_id 的映射
    private const GROUP_NAME_MAP = [
        'Sunesis' => 420,  // 驱逐舰
        'Gnosis' => 419,   // 战列巡洋舰
        'Violator' => 420,
        'Bestla' => 547,   // 航母
        'Mime' => 833,     // 侦察舰
        'Chameleon' => 833,
        'Whiptail' => 831, // 拦截舰
        'Victor' => 833,
        'Tiamat' => 833,
        'Adrestia' => 358, // 重型突击巡洋舰
        'Mimir' => 358,
        'Virtuoso' => 894, // 重型拦截巡洋舰
        'Camel' => 540,    // 指挥舰
        'Gabriel' => 27,   // 战列舰 - 巨像用特殊group
        'Sutore' => 27,
        'Striel' => 27,
        'Moonsblood' => 27,
    ];

    // 特殊舰船的 group_id（根据 eve_items.json 分类推断）
    private const SPECIAL_GROUP_IDS = [
        // 巨像 - 战列舰级别
        78589 => 27,  // 加百列级
        84925 => 27,  // 索洛托尔级
        86485 => 27,  // 斯特利亚级
        89107 => 27,  // 血月级
        // 特别版侦察舰
        81386 => 833, // ★变色龙级
        84004 => 833, // ★胜利者级
        84373 => 833, // ★提亚玛特级
        // 特别版截击舰
        82192 => 831, // ★长尾蜥级
        // 特别版突击护卫舰
        84219 => 324, // ★暴狼级
        // 特别版掠夺舰
        77500 => 900, // 巡察者级
        80983 => 900, // 狄瓦娜级
        // 特别版航空母舰
        76009 => 547, // 山岳级
        // 特别版重型拦截巡洋舰
        77980 => 894, // 虚妄级
        // 特别版战列巡洋舰
        89807 => 419, // 蛇鹈级
        // 特别版驱逐舰
        89808 => 420, // 贼鸥级
        91174 => 420, // 坚韧级
    ];

    public function handle(): int
    {
        set_time_limit(600);

        $dryRun = $this->option('dry-run');
        $idsOption = $this->option('ids');

        // 获取要添加的舰船ID
        $shipIds = $idsOption 
            ? array_map('intval', explode(',', $idsOption))
            : self::NEW_SHIP_IDS;

        $this->info('=== 从 ESI 添加新舰船 ===');
        $this->info('要添加的舰船数量: ' . count($shipIds));

        // 加载本地JSON获取中文名
        $localItems = $this->loadLocalJson();
        $this->info('本地JSON加载完成: ' . count($localItems) . ' 个物品');

        // 连接数据库
        $fittingDbPath = database_path('fitting.sqlite');
        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 检查已存在的舰船
        $existingIds = $pdo->query("SELECT type_id FROM fitting_types WHERE type_id IN (" . implode(',', $shipIds) . ")")
            ->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($existingIds)) {
            $this->warn('以下舰船已存在，将跳过: ' . implode(', ', $existingIds));
            $shipIds = array_diff($shipIds, $existingIds);
        }

        if (empty($shipIds)) {
            $this->info('没有需要添加的舰船');
            return 0;
        }

        $added = 0;
        $failed = 0;

        foreach ($shipIds as $typeId) {
            $this->line("处理舰船 {$typeId}...");

            // 从本地JSON获取中文名
            $nameCn = $localItems[$typeId]['name'] ?? null;
            
            // 从 ESI 获取详细数据
            $esiData = $this->fetchFromEsi($typeId);

            if (!$esiData) {
                $this->error("  无法从 ESI 获取数据");
                $failed++;
                continue;
            }

            // 推断 group_id
            $groupId = self::SPECIAL_GROUP_IDS[$typeId] ?? $this->inferGroupId($esiData, $localItems[$typeId] ?? []);

            if ($dryRun) {
                $this->info("  [DRY-RUN] 将添加: {$esiData['name']} ({$nameCn})");
                $this->line("    group_id: {$groupId}, category_id: 6");
                $added++;
                continue;
            }

            // 插入数据库
            try {
                $this->insertShip($pdo, $typeId, $esiData, $nameCn, $groupId);
                $this->info("  已添加: {$esiData['name']} ({$nameCn})");
                $added++;
            } catch (\Exception $e) {
                $this->error("  插入失败: " . $e->getMessage());
                $failed++;
            }

            // 避免请求过快
            usleep(100000); // 100ms
        }

        $this->newLine();
        $this->info("=== 完成 ===");
        $this->info("添加: {$added}, 失败: {$failed}");

        // 清除缓存
        if (!$dryRun && $added > 0) {
            Cache::forget('fitting_ship_categories');
            Cache::forget('fitting_ship_tree');
            $this->info('已清除缓存');
        }

        return 0;
    }

    /**
     * 加载本地JSON数据
     */
    private function loadLocalJson(): array
    {
        if (!file_exists(self::ITEMS_JSON)) {
            return [];
        }

        $content = file_get_contents(self::ITEMS_JSON);
        return json_decode($content, true) ?? [];
    }

    /**
     * 从 ESI 获取舰船数据
     */
    private function fetchFromEsi(int $typeId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::ESI_BASE . "/latest/universe/types/{$typeId}/");

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 推断 group_id
     */
    private function inferGroupId(array $esiData, array $localItem): int
    {
        // 优先使用 ESI 返回的 group_id
        if (isset($esiData['group_id'])) {
            return $esiData['group_id'];
        }

        // 根据 eve_items.json 的分类推断
        if (isset($localItem['category'])) {
            $cat = $localItem['category'];
            // 分类结构：[舰船, 巡洋舰, 特别版巡洋舰, ...]
            if (count($cat) >= 2) {
                $shipType = $cat[1]; // 如 "战列舰"、"巡洋舰"
                return $this->mapShipTypeToGroupId($shipType);
            }
        }

        // 默认返回护卫舰
        return 25;
    }

    /**
     * 舰船类型到 group_id 的映射
     */
    private function mapShipTypeToGroupId(string $shipType): int
    {
        $map = [
            '护卫舰' => 25,
            '轻型护卫舰' => 237,
            '驱逐舰' => 420,
            '巡洋舰' => 26,
            '战列巡洋舰' => 419,
            '战列舰' => 27,
            '航空母舰' => 547,
            '超级航母' => 659,
            '泰坦' => 30,
            '运载舰和工业舰' => 28,
            '采矿驳船' => 463,
            '穿梭机' => 31,
        ];

        return $map[$shipType] ?? 25;
    }

    /**
     * 插入舰船数据
     */
    private function insertShip(PDO $pdo, int $typeId, array $esiData, ?string $nameCn, int $groupId): void
    {
        // 插入 fitting_types
        $stmt = $pdo->prepare("
            INSERT INTO fitting_types (type_id, group_id, category_id, name_en, name_cn, mass, volume, capacity, description, published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $typeId,
            $groupId,
            self::SHIP_CATEGORY_ID,
            $esiData['name'] ?? '',
            $nameCn ?? $esiData['name'] ?? '',
            $esiData['mass'] ?? 0,
            $esiData['volume'] ?? 0,
            $esiData['packaged_volume'] ?? 0,
            $esiData['description'] ?? '',
        ]);

        // 插入属性值（如果有）
        if (isset($esiData['dogma_attributes']) && !empty($esiData['dogma_attributes'])) {
            $attrStmt = $pdo->prepare("
                INSERT OR REPLACE INTO fitting_attributes (type_id, attribute_id, value_int, value_float)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($esiData['dogma_attributes'] as $attr) {
                $attrId = $attr['attribute_id'];
                $value = $attr['value'];
                
                $attrStmt->execute([
                    $typeId,
                    $attrId,
                    is_int($value) ? $value : null,
                    is_float($value) ? $value : null,
                ]);
            }
        }

        // 确保 group 存在
        $this->ensureGroupExists($pdo, $groupId);
    }

    /**
     * 确保 group 存在
     */
    private function ensureGroupExists(PDO $pdo, int $groupId): void
    {
        $exists = $pdo->query("SELECT 1 FROM fitting_groups WHERE group_id = {$groupId}")->fetchColumn();
        
        if (!$exists) {
            $nameCn = $this->getGroupNameCn($groupId);
            $pdo->prepare("INSERT INTO fitting_groups (group_id, category_id, name_en, name_cn, published) VALUES (?, 6, ?, ?, 1)")
                ->execute([$groupId, "Custom Group {$groupId}", $nameCn]);
        }
    }

    /**
     * 获取组的中文名
     */
    private function getGroupNameCn(int $groupId): string
    {
        $names = [
            27 => '战列舰',
            420 => '驱逐舰',
            419 => '战列巡洋舰',
            547 => '航母',
            831 => '拦截舰',
            833 => '侦察舰',
            324 => '突击护卫舰',
            900 => '掠夺舰',
            894 => '重型拦截巡洋舰',
        ];

        return $names[$groupId] ?? "自定义组 {$groupId}";
    }
}