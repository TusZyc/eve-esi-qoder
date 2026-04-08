<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PDO;

class TranslateFittingNames extends Command
{
    protected $signature = 'fitting:translate-names
        {--force : 强制重新翻译所有物品}
        {--batch=100 : 每批处理数量}
        {--esi-fallback : 启用ESI兜底（JSON中没有的物品）}';

    protected $description = '使用本地JSON数据翻译物品名称，ESI API兜底';

    // 国服ESI API地址
    private const ESI_BASE = 'https://esi.evepc.163.com';

    // 本地JSON文件路径
    private const ITEMS_JSON = '/var/www/html/data/eve_items.json';

    public function handle()
    {
        set_time_limit(600);

        $force = $this->option('force');
        $batchSize = (int)$this->option('batch');
        $esiFallback = $this->option('esi-fallback');

        $fittingDbPath = database_path('fitting.sqlite');

        if (!file_exists($fittingDbPath)) {
            $this->error('fitting.sqlite 数据库不存在，请先运行 fitting:import-sde');
            return 1;
        }

        $this->info('=== 开始翻译物品名称 ===');

        // 加载本地JSON数据
        $localItems = $this->loadLocalJson();
        $this->info("本地JSON加载完成: " . count($localItems) . " 个物品");

        // 连接数据库
        $pdo = new PDO('sqlite:' . $fittingDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 获取需要翻译的物品
        $typeIds = $this->getTypesToTranslate($pdo, $force);
        $this->info("需要翻译: " . count($typeIds) . " 个物品");

        // 执行翻译
        $this->translateItems($pdo, $typeIds, $localItems, $esiFallback, $batchSize);

        $this->info('=== 翻译完成 ===');
        $this->showStats($pdo);

        return 0;
    }

    /**
     * 加载本地JSON数据
     */
    private function loadLocalJson(): array
    {
        $jsonPath = self::ITEMS_JSON;

        if (!file_exists($jsonPath)) {
            $this->warn("本地JSON文件不存在: {$jsonPath}");
            return [];
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON解析失败: " . json_last_error_msg());
            return [];
        }

        return $data;
    }

    /**
     * 获取需要翻译的物品ID
     */
    private function getTypesToTranslate(PDO $pdo, bool $force): array
    {
        if ($force) {
            // 强制模式：翻译所有物品
            $stmt = $pdo->query("SELECT type_id FROM fitting_types");
        } else {
            // 只翻译 name_cn 为空的物品
            $stmt = $pdo->query("SELECT type_id FROM fitting_types WHERE name_cn IS NULL OR name_cn = ''");
        }

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 执行翻译
     */
    private function translateItems(PDO $pdo, array $typeIds, array $localItems, bool $esiFallback, int $batchSize): void
    {
        $updateStmt = $pdo->prepare("UPDATE fitting_types SET name_cn = ? WHERE type_id = ?");

        $translated = 0;
        $fromJson = 0;
        $fromEsi = 0;
        $failed = 0;

        $pdo->beginTransaction();

        foreach ($typeIds as $index => $typeId) {
            $nameCn = null;

            // 优先使用本地JSON（JSON键是字符串，需要转换）
            $typeIdStr = (string)$typeId;
            if (isset($localItems[$typeIdStr]) && isset($localItems[$typeIdStr]['name'])) {
                $nameCn = $localItems[$typeIdStr]['name'];
                $fromJson++;
            }

            // JSON中没有，使用ESI兜底
            if (!$nameCn && $esiFallback) {
                $nameCn = $this->fetchFromEsi($typeId);
                if ($nameCn) {
                    $fromEsi++;
                }
            }

            // 更新数据库
            if ($nameCn) {
                $updateStmt->execute([$nameCn, $typeId]);
                $translated++;
            } else {
                $failed++;
            }

            // 进度显示和批量提交
            if (($index + 1) % $batchSize === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
                $this->info("进度: {$translated}/" . count($typeIds) . " (JSON: {$fromJson}, ESI: {$fromEsi}, 失败: {$failed})");
            }
        }

        $pdo->commit();

        $this->newLine();
        $this->info("翻译结果:");
        $this->info("  - 本地JSON: {$fromJson}");
        $this->info("  - ESI兜底: {$fromEsi}");
        $this->info("  - 失败: {$failed}");
    }

    /**
     * 从ESI API获取中文名称
     */
    private function fetchFromEsi(int $typeId): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get(self::ESI_BASE . "/universe/types/{$typeId}/?language=zh");

            if ($response->successful()) {
                $data = $response->json();
                return $data['name'] ?? null;
            }
        } catch (\Exception $e) {
            // 静默失败，记录到失败计数
        }

        return null;
    }

    /**
     * 显示统计信息
     */
    private function showStats(PDO $pdo): void
    {
        $total = $pdo->query("SELECT COUNT(*) FROM fitting_types")->fetchColumn();
        $translated = $pdo->query("SELECT COUNT(*) FROM fitting_types WHERE name_cn IS NOT NULL AND name_cn != ''")->fetchColumn();
        $untranslated = $total - $translated;

        $this->newLine();
        $this->info("=== 数据库统计 ===");
        $this->info("总物品数: {$total}");
        $this->info("已翻译: {$translated}");
        $this->info("未翻译: {$untranslated}");
    }
}