<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PDO;

/**
 * 从国服ESI获取新舰船数据并添加到数据库
 */
class AddNewShipsFromEsi extends Command
{
    protected $signature = 'fitting:add-new-ships
        {--dry-run : 只显示将要添加的舰船，不实际添加}';

    protected $description = '从国服ESI获取新舰船数据并添加到数据库';

    // 国服ESI地址
    private const ESI_BASE = 'https://ali-esi.evepc.163.com';
    
    // 本地JSON文件路径（用于获取中文名）
    private const ITEMS_JSON = '/var/www/html/data/eve_items.json';

    // 新舰船ID列表
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

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fittingDbPath = database_path('fitting.sqlite');

        if (!file_exists($fittingDbPath)) {
            $this->error('fitting.sqlite 数据库不存在');
            return 1;
        }

        $this->info('=== 从国服ESI获取新舰船 ===');

        // 加载本地JSON获取中文名
        $localItems = $this->loadLocalJson();
        $this->info('本地JSON加载完成: ' . count($localItems) . ' 个物品');

        // 连接数据库
        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取数据库中已有的舰船ID
        $existingIds = $pdo->query("SELECT type_id FROM fitting_types WHERE category_id = 6")
            ->fetchAll(PDO::FETCH_COLUMN);

        // 找出需要添加的舰船
        $toAdd = array_diff(self::NEW_SHIP_IDS, $existingIds);
        $toAdd = array_unique($toAdd);

        $this->info('需要添加 ' . count($toAdd) . ' 艘新舰船');

        if (empty($toAdd)) {
            $this->info('没有新舰船需要添加');
            return 0;
        }

        $added = 0;
        $failed = 0;

        foreach ($toAdd as $typeId) {
            $this->line("处理舰船 {$typeId}...");

            // 从ESI获取数据
            $esiData = $this->fetchFromEsi($typeId);

            if (!$esiData) {
                $this->error("  无法从ESI获取数据");
                $failed++;
                continue;
            }

            // 从本地JSON获取中文名
            $nameCn = $localItems[$typeId]['name'] ?? $esiData['name'];
            $groupId = $esiData['group_id'] ?? null;

            $this->info("  {$esiData['name']} ({$nameCn})");
            $this->line("    group_id: {$groupId}");

            if ($dryRun) {
                $added++;
                continue;
            }

            // 插入数据库
            try {
                $pdo->beginTransaction();

                // 插入 fitting_types
                $stmt = $pdo->prepare("
                    INSERT INTO fitting_types (type_id, group_id, category_id, name_en, name_cn, mass, volume, capacity, description, published)
                    VALUES (?, ?, 6, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $typeId,
                    $groupId,
                    $esiData['name'] ?? '',
                    $nameCn,
                    $esiData['mass'] ?? 0,
                    $esiData['volume'] ?? 0,
                    $esiData['capacity'] ?? 0,
                    $esiData['description'] ?? '',
                    $esiData['published'] ? 1 : 0,
                ]);

                // 插入属性值
                if (!empty($esiData['dogma_attributes'])) {
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
                            is_float($value) ? $value : (float)$value,
                        ]);
                    }
                }

                // 插入效果映射
                if (!empty($esiData['dogma_effects'])) {
                    $effectStmt = $pdo->prepare("
                        INSERT OR IGNORE INTO fitting_type_effects (type_id, effect_id, is_default)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($esiData['dogma_effects'] as $effect) {
                        $effectStmt->execute([
                            $typeId,
                            $effect['effect_id'],
                            $effect['is_default'] ? 1 : 0,
                        ]);
                    }
                }

                $pdo->commit();
                $added++;
                $this->info("  已添加");

            } catch (\Exception $e) {
                $pdo->rollBack();
                $this->error("  插入失败: " . $e->getMessage());
                $failed++;
            }

            // 避免请求过快
            usleep(200000); // 200ms
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
     * 从国服ESI获取舰船数据
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
}