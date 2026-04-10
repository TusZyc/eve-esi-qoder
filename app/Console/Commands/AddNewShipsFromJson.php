<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PDO;

/**
 * 从 eve_items.json 添加新舰船到数据库
 */
class AddNewShipsFromJson extends Command
{
    protected $signature = 'fitting:add-new-ships
        {--dry-run : 只显示将要添加的舰船，不实际添加}';

    protected $description = '从 eve_items.json 添加数据库中不存在的新舰船';

    private const ITEMS_JSON = '/var/www/html/data/eve_items.json';

    // 新舰船ID列表（eve_items.json中存在但SDE中不存在的）
    private const NEW_SHIP_IDS = [
        // 巨像系列（特别版战列舰）
        78589, 84925, 86485, 89107,
        // 特别版战列巡洋舰
        89807,
        // 特别版驱逐舰
        89808, 91174,
        // 特别版掠夺舰
        77500, 80983,
        // 特别版航空母舰
        76009,
        // 特别版截击舰
        82192,
        // 特别版侦察舰
        81386, 84004, 84373, 85062, 85229, 85236,
        // 特别版突击护卫舰
        84219,
        // 特别版重型拦截巡洋舰
        77980,
    ];

    // group_id 映射（根据舰船类型推断）
    private const GROUP_ID_MAP = [
        '巨像' => 27,           // 战列舰
        '特别版战列巡洋舰' => 419,
        '特别版驱逐舰' => 420,
        '特别版掠夺舰' => 900,
        '特别版航空母舰' => 547,
        '特别版截击舰' => 831,
        '特别版侦察舰' => 833,
        '特别版突击护卫舰' => 324,
        '特别版重型突击巡洋舰' => 358,
        '特别版重型拦截巡洋舰' => 894,
        '特别版后勤舰' => 832,
        '特别版隐形特勤舰' => 830,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fittingDbPath = database_path('fitting.sqlite');

        if (!file_exists($fittingDbPath)) {
            $this->error('fitting.sqlite 数据库不存在');
            return 1;
        }

        $this->info('=== 从 eve_items.json 添加新舰船 ===');

        // 加载本地JSON
        $items = $this->loadLocalJson();
        if (empty($items)) {
            $this->error('无法加载 eve_items.json');
            return 1;
        }
        $this->info('加载 ' . count($items) . ' 个物品');

        // 连接数据库
        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取数据库中已有的舰船ID
        $existingIds = $pdo->query("SELECT type_id FROM fitting_types WHERE category_id = 6")
            ->fetchAll(PDO::FETCH_COLUMN);

        // 找出需要添加的舰船
        $toAdd = [];
        foreach (self::NEW_SHIP_IDS as $shipId) {
            if (!in_array($shipId, $existingIds) && isset($items[$shipId])) {
                $toAdd[] = $shipId;
            }
        }
        
        // 去重
        $toAdd = array_unique($toAdd);

        $this->info('需要添加 ' . count($toAdd) . ' 艘新舰船');

        if (empty($toAdd)) {
            $this->info('没有新舰船需要添加');
            return 0;
        }

        // 添加舰船
        $added = 0;
        foreach ($toAdd as $shipId) {
            $item = $items[$shipId];
            $nameCn = $item['name'] ?? '';
            $category = $item['category'] ?? [];

            // 推断 group_id
            $groupId = $this->inferGroupId($category);

            // 获取英文描述或使用中文名
            $description = "特别版舰船 - {$nameCn}";

            $this->line("{$shipId}: {$nameCn}");
            $this->line("  分类: " . implode(' > ', $category));
            $this->line("  group_id: {$groupId}");

            if (!$dryRun && $groupId) {
                // 插入数据库
                $stmt = $pdo->prepare("
                    INSERT INTO fitting_types (type_id, group_id, category_id, name_en, name_cn, description, published)
                    VALUES (?, ?, 6, ?, ?, ?, 1)
                ");
                $stmt->execute([$shipId, $groupId, $nameCn, $nameCn, $description]);
                $added++;
            }
        }

        if (!$dryRun) {
            $this->info("成功添加 {$added} 艘新舰船");

            // 清除缓存
            Cache::forget('fitting_ship_categories');
            Cache::forget('fitting_ship_tree');
            $this->info('已清除缓存');
        }

        return 0;
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

    /**
     * 根据分类推断 group_id
     */
    private function inferGroupId(array $category): ?int
    {
        foreach ($category as $cat) {
            if (isset(self::GROUP_ID_MAP[$cat])) {
                return self::GROUP_ID_MAP[$cat];
            }
        }
        return null;
    }
}