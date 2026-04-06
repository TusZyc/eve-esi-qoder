<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// 显式引入 PHP GD 全局函数
use function imagecreatetruecolor;
use function imagesavealpha;
use function imagealphablending;
use function imagefilledrectangle;
use function imageline;
use function imagepng;
use function imagedestroy;

/**
 * KM 图片生成服务 - 门面模式
 * 
 * 委托 KillmailImageRenderer 处理具体绘制逻辑
 * 本类保留入口方法和缓存管理
 * 
 * [重构优化] 2026-04-06: 提取绘制逻辑到 KillmailImageRenderer
 */
class KillmailImageService
{
    protected KillmailImageRenderer $renderer;
    protected string $cachePath;

    public function __construct(KillmailImageRenderer $renderer)
    {
        $this->renderer  = $renderer;
        $this->cachePath = storage_path('app/km-images');

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  生成入口
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 生成 KM 图片
     * 
     * @param array $killData KM 数据
     * @param bool $force 是否强制重新生成
     * @return string 图片路径
     */
    public function generate(array $killData, bool $force = false): string
    {
        $killId     = $killData['kill_id'];
        $cachedPath = $this->cachePath . "/km_{$killId}.png";

        if (!$force && file_exists($cachedPath)) {
            return $cachedPath;
        }

        try {
            // 提高内存限制（复杂KM可能需要更多内存）
            $memoryLimit = ini_get('memory_limit');
            if ($memoryLimit && (int)$memoryLimit < 256) {
                @ini_set('memory_limit', '256M');
            }

            $attackers   = $killData['attackers']    ?? [];
            $itemsBySlot = $killData['items_by_slot'] ?? [];
            $victim      = $killData['victim']        ?? [];

            // [修复] 限制装备项数量，避免图片过大导致内存溢出
            $itemsBySlot = $this->limitItems($itemsBySlot, 100);

            // 计算动态高度
            $leftH    = $this->renderer->calcAttackerH($attackers);
            $rightH   = $this->renderer->calcItemH($itemsBySlot);
            $contentH = max($leftH, $rightH) + KillmailImageRenderer::PADDING * 2;

            // [修复] 限制最大高度，避免创建超大画布
            $maxContentH = 2000; // 最大内容高度
            if ($contentH > $maxContentH) {
                $contentH = $maxContentH;
                Log::debug("KM image: 内容高度被限制为 {$maxContentH}px");
            }

            $totalH = KillmailImageRenderer::TITLE_BAR_HEIGHT 
                    + KillmailImageRenderer::HEADER_HEIGHT
                    + KillmailImageRenderer::STATS_BAR_HEIGHT 
                    + $contentH 
                    + KillmailImageRenderer::FOOTER_HEIGHT;

            // 创建画布
            $img = imagecreatetruecolor(KillmailImageRenderer::CANVAS_WIDTH, $totalH);
            if (!$img) {
                throw new \RuntimeException("无法创建图片画布，尺寸: 900x{$totalH}");
            }
            
            imagesavealpha($img, true);
            imagealphablending($img, true);

            // 纯黑背景
            $bg = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, KillmailImageRenderer::CANVAS_WIDTH - 1, $totalH - 1, $bg);

            // 委托给渲染器绘制各区域
            $this->renderer->drawTitleBar($img, $killData);
            $this->renderer->drawHeader($img, $killData);
            $this->renderer->drawStatsBar($img, $killData);

            $contentY = KillmailImageRenderer::TITLE_BAR_HEIGHT 
                      + KillmailImageRenderer::HEADER_HEIGHT 
                      + KillmailImageRenderer::STATS_BAR_HEIGHT;
            $this->renderer->drawAttackers($img, $attackers, $contentY, (int)($victim['damage_taken'] ?? 0));
            $this->renderer->drawItems($img, $itemsBySlot, $contentY);
            $this->renderer->drawFooter($img, $killData, $totalH);

            imagepng($img, $cachedPath, 5);
            imagedestroy($img);

            return $cachedPath;
        } catch (\Throwable $e) {
            // 清理可能残留的GD资源
            if (isset($img) && $img) {
                @imagedestroy($img);
            }
            
            Log::error("KM 图片生成失败 (killId={$killId}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // 返回错误占位图
            return $this->generateErrorPlaceholder($killId, $e->getMessage());
        }
    }

    /**
     * 限制装备项数量，避免图片过大
     * 
     * @param array $itemsBySlot 按槽位分组的装备
     * @param int $maxItems 最大装备项数量
     * @return array 限制后的装备列表
     */
    protected function limitItems(array $itemsBySlot, int $maxItems): array
    {
        $totalItems = 0;
        $limited = [];
        
        foreach ($itemsBySlot as $slotName => $items) {
            if (empty($items)) continue;
            
            $slotCount = count($items);
            if ($totalItems + $slotCount > $maxItems) {
                // 只保留能容纳的部分
                $remaining = $maxItems - $totalItems;
                if ($remaining > 0) {
                    $limited[$slotName] = array_slice($items, 0, $remaining);
                    $limited[$slotName . '_截断'] = [
                        ['item_name' => '... (已截断)', 'item_type_id' => 0, 'quantity' => $slotCount - $remaining]
                    ];
                }
                break;
            }
            
            $limited[$slotName] = $items;
            $totalItems += $slotCount;
        }
        
        return $limited;
    }

    /**
     * 生成错误占位图
     */
    protected function generateErrorPlaceholder(int $killId, string $errorMsg): string
    {
        $path = $this->cachePath . "/km_{$killId}_error.png";
        
        $img = @imagecreatetruecolor(400, 200);
        if (!$img) {
            // 如果连占位图都创建不了，返回空路径
            return '';
        }
        
        $bg = imagecolorallocate($img, 30, 30, 40);
        $textColor = imagecolorallocate($img, 200, 80, 80);
        $borderColor = imagecolorallocate($img, 80, 80, 100);
        
        imagefilledrectangle($img, 0, 0, 399, 199, $bg);
        imagerectangle($img, 0, 0, 399, 199, $borderColor);
        
        // 使用简单字体（不依赖TTF）
        $msg = "KM #{$killId} 图片生成失败";
        imagestring($img, 5, 20, 60, $msg, $textColor);
        
        $shortErr = mb_substr($errorMsg, 0, 50);
        imagestring($img, 3, 20, 90, $shortErr, imagecolorallocate($img, 150, 150, 150));
        
        imagepng($img, $path);
        imagedestroy($img);
        
        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  缓存管理
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 清除指定 KM 的图片缓存
     */
    public function clearCache(int $killId): void
    {
        $path = $this->cachePath . "/km_{$killId}.png";
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * 清除所有 KM 图片缓存
     */
    public function clearAllCache(): void
    {
        $files = glob($this->cachePath . '/km_*.png');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * 获取缓存路径
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }
}