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

        $attackers   = $killData['attackers']    ?? [];
        $itemsBySlot = $killData['items_by_slot'] ?? [];
        $victim      = $killData['victim']        ?? [];

        // 计算动态高度
        $leftH    = $this->renderer->calcAttackerH($attackers);
        $rightH   = $this->renderer->calcItemH($itemsBySlot);
        $contentH = max($leftH, $rightH) + KillmailImageRenderer::PADDING * 2;

        $totalH = KillmailImageRenderer::TITLE_BAR_HEIGHT 
                + KillmailImageRenderer::HEADER_HEIGHT
                + KillmailImageRenderer::STATS_BAR_HEIGHT 
                + $contentH 
                + KillmailImageRenderer::FOOTER_HEIGHT;

        // 创建画布
        $img = imagecreatetruecolor(KillmailImageRenderer::CANVAS_WIDTH, $totalH);
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