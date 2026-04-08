<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PDO;

class UpdateShipAttributes extends Command
{
    protected $signature = 'fitting:update-ship-attrs
        {--ids= : 指定要更新的舰船ID，逗号分隔}
        {--missing : 只更新缺少属性的舰船}';

    protected $description = '从国服ESI更新舰船属性数据';

    private const ESI_BASE = 'https://ali-esi.evepc.163.com';

    public function handle(): int
    {
        $idsOption = $this->option('ids');
        $missingOnly = $this->option('missing');

        $fittingDbPath = database_path('fitting.sqlite');

        if (!file_exists($fittingDbPath)) {
            $this->error('fitting.sqlite 数据库不存在');
            return 1;
        }

        $this->info('=== 从国服ESI更新舰船属性 ===');

        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取要更新的舰船ID
        if ($idsOption) {
            $shipIds = array_map('intval', explode(',', $idsOption));
        } else {
            // 获取所有没有属性的舰船
            $shipIds = $pdo->query("
                SELECT t.type_id 
                FROM fitting_types t 
                LEFT JOIN fitting_attributes a ON t.type_id = a.type_id 
                WHERE t.category_id = 6 AND a.type_id IS NULL
            ")->fetchAll(PDO::FETCH_COLUMN);
        }

        $this->info('需要更新 ' . count($shipIds) . ' 艘舰船的属性');

        if (empty($shipIds)) {
            $this->info('没有需要更新的舰船');
            return 0;
        }

        $updated = 0;
        $failed = 0;

        foreach ($shipIds as $typeId) {
            $this->line("处理舰船 {$typeId}...");

            $esiData = $this->fetchFromEsi($typeId);

            if (!$esiData) {
                $this->error("  无法从ESI获取数据");
                $failed++;
                continue;
            }

            $ship = $pdo->query("SELECT name_cn FROM fitting_types WHERE type_id = {$typeId}")->fetch(PDO::FETCH_ASSOC);
            $nameCn = $ship['name_cn'] ?? $esiData['name'];

            $this->info("  {$esiData['name']} ({$nameCn})");

            try {
                $pdo->beginTransaction();

                // 更新 fitting_types
                $updateStmt = $pdo->prepare("UPDATE fitting_types SET mass = ?, volume = ?, capacity = ?, description = ? WHERE type_id = ?");
                $updateStmt->execute([
                    $esiData['mass'] ?? 0,
                    $esiData['volume'] ?? 0,
                    $esiData['capacity'] ?? 0,
                    $esiData['description'] ?? '',
                    $typeId,
                ]);

                // 插入属性值
                if (!empty($esiData['dogma_attributes'])) {
                    $attrStmt = $pdo->prepare("INSERT OR REPLACE INTO fitting_attributes (type_id, attribute_id, value_int, value_float) VALUES (?, ?, ?, ?)");

                    foreach ($esiData['dogma_attributes'] as $attr) {
                        $value = $attr['value'];
                        $attrStmt->execute([
                            $typeId,
                            $attr['attribute_id'],
                            is_int($value) ? $value : null,
                            is_float($value) ? $value : (float)$value,
                        ]);
                    }
                }

                // 插入效果映射
                if (!empty($esiData['dogma_effects'])) {
                    $effectStmt = $pdo->prepare("INSERT OR IGNORE INTO fitting_type_effects (type_id, effect_id, is_default) VALUES (?, ?, ?)");

                    foreach ($esiData['dogma_effects'] as $effect) {
                        $effectStmt->execute([
                            $typeId,
                            $effect['effect_id'],
                            $effect['is_default'] ? 1 : 0,
                        ]);
                    }
                }

                $pdo->commit();
                $updated++;
                $this->info("  已更新");

            } catch (\Exception $e) {
                $pdo->rollBack();
                $this->error("  更新失败: " . $e->getMessage());
                $failed++;
            }

            usleep(200000);
        }

        $this->newLine();
        $this->info("=== 完成 ===");
        $this->info("更新: {$updated}, 失败: {$failed}");

        if ($updated > 0) {
            Cache::forget('fitting_ship_categories');
            Cache::forget('fitting_ship_tree');
            $this->info('已清除缓存');
        }

        return 0;
    }

    private function fetchFromEsi(int $typeId): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::ESI_BASE . "/latest/universe/types/{$typeId}/");
            if (!$response->successful()) {
                return null;
            }
            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }
}