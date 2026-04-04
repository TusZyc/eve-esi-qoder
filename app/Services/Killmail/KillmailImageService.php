<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// 显式引入 PHP GD 全局函数，避免命名空间查找失败
// [Claude Code] 2026-04-04
use function imagecreatetruecolor;
use function imagesavealpha;
use function imagealphablending;
use function imagefilledrectangle;
use function imageline;
use function imagepng;
use function imagedestroy;
use function imagerectangle;
use function imagecreatefromstring;
use function imagecreatefrompng;
use function imagecreatefromjpeg;
use function imagettftext;
use function imagettfbbox;
use function imagestring;
use function imagesx;
use function imagesy;
use function imagecopyresampled;
use function imagecopy;
use function imagecolorallocate;
use function imagecolorallocatealpha;

/**
 * KM 图片生成服务 - 仿游戏内击毁报告 UI 风格
 * [Claude Code] 2026-04-04
 *
 * 使用 PHP GD 库 + TTF 字体渲染，无需浏览器自动化
 * 布局参考 EVE Online 游戏内击毁报告窗口
 *
 * 布局结构 (900px 宽):
 * ┌──────────────────────────────────────────┐
 * │  顶部: 角色头像 + 舰船渲染图 + 基本信息  │ ~180px
 * ├──────────────────────────────────────────┤
 * │  统计栏: 参与者数 / 总伤害              │ ~35px
 * ├───────────────┬──────────────────────────┤
 * │  参与者列表    │  装备与明细              │ 动态高度
 * │  (头像+名+伤害)│  (按槽位分组)            │
 * ├───────────────┴──────────────────────────┤
 * │  底部: 总价值                            │ ~45px
 * └──────────────────────────────────────────┘
 */
class KillmailImageService
{
    // ============ 画布尺寸常量 ============
    const CANVAS_WIDTH = 900;
    const PADDING = 16;
    const TITLE_BAR_HEIGHT = 32;      // 窗口标题栏 "击毁报告"
    const HEADER_HEIGHT = 180;         // 角色信息区
    const STATS_BAR_HEIGHT = 35;
    const FOOTER_HEIGHT = 50;

    // ============ 颜色常量 (RGBA) ============
    // 游戏内UI深色主题
    const COLOR_BG = [15, 15, 25];              // 深蓝黑背景
    const COLOR_PANEL = [25, 30, 45];            // 面板背景
    const COLOR_PANEL_HEADER = [30, 38, 55];     // 面板标题背景
    const COLOR_BORDER = [50, 60, 85];           // 边框线
    const COLOR_TEXT_PRIMARY = [220, 225, 235];   // 主要文字 (白)
    const COLOR_TEXT_SECONDARY = [140, 150, 170]; // 次要文字 (灰蓝)
    const COLOR_TEXT_HIGHLIGHT = [255, 200, 50];  // 高亮文字 (金)
    const COLOR_TEXT_RED = [220, 60, 60];         // 红色 (伤害)
    const COLOR_TEXT_GREEN = [60, 200, 120];      // 绿色 (安全等级)
    const COLOR_TEXT_BLUE = [80, 160, 255];       // 蓝色 (链接)
    const COLOR_SLOT_HEADER = [45, 55, 75];       // 槽位标题背景
    const COLOR_FINAL_BLOW = [180, 140, 50];      // 最后一击标记
    const COLOR_DESTROYED = [180, 50, 50, 80];    // 已销毁物品背景
    const COLOR_DROPPED = [50, 150, 50, 80];      // 已坠落物品背景
    const COLOR_DIVIDER = [40, 50, 70];           // 分隔线

    // ============ 字体大小 ============
    const FONT_SIZE_TITLE = 18;
    const FONT_SIZE_NORMAL = 13;
    const FONT_SIZE_SMALL = 11;
    const FONT_SIZE_TINY = 10;
    const FONT_SIZE_HEADER = 15;
    const FONT_SIZE_VALUE = 16;

    // ============ 图标尺寸 ============
    const PORTRAIT_SIZE = 128;        // 头部角色头像
    const SHIP_RENDER_SIZE = 200;     // 头部舰船渲染图
    const ATTACKER_ICON_SIZE = 32;    // 攻击者头像
    const ITEM_ICON_SIZE = 32;        // 物品图标
    const CORP_ICON_SIZE = 32;        // 军团图标

    // ============ 列宽 ============
    const LEFT_COL_WIDTH = 380;       // 左栏（参与者）
    const RIGHT_COL_WIDTH = 500;      // 右栏（装备）= 900 - 380 - 20(间距)

    // ============ EVE 图片 API ============
    const EVE_IMAGE_BASE = 'https://images.evetech.net';

    protected string $fontPath;
    protected string $fontBoldPath;
    protected string $cachePath;

    public function __construct()
    {
        $this->fontPath = storage_path('fonts/NotoSansSC-Regular.ttf');
        $this->fontBoldPath = storage_path('fonts/NotoSansSC-Bold.ttf');
        $this->cachePath = storage_path('app/km-images');

        // 确保缓存目录存在
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * 生成 KM 图片
     *
     * @param array $killData 来自 KillmailService::buildKillDetailResponse() 的数据
     * @return string 生成的图片文件路径
     */
    public function generate(array $killData): string
    {
        $killId = $killData['kill_id'];

        // 检查缓存
        $cachedPath = $this->cachePath . "/km_{$killId}.png";
        if (file_exists($cachedPath)) {
            return $cachedPath;
        }

        // 1. 预处理数据，计算动态高度
        $attackers = $killData['attackers'] ?? [];
        $itemsBySlot = $killData['items_by_slot'] ?? [];
        $victim = $killData['victim'] ?? [];

        $leftHeight = $this->calculateAttackerListHeight($attackers);
        $rightHeight = $this->calculateItemListHeight($itemsBySlot);
        $contentHeight = max($leftHeight, $rightHeight);

        $totalHeight = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT + self::STATS_BAR_HEIGHT
                      + $contentHeight + self::FOOTER_HEIGHT + self::PADDING * 2;

        // 2. 创建画布
        $img = imagecreatetruecolor(self::CANVAS_WIDTH, $totalHeight);
        imagesavealpha($img, true);
        imagealphablending($img, true);

        // 3. 填充背景
        $bgColor = $this->allocColor($img, self::COLOR_BG);
        imagefilledrectangle($img, 0, 0, self::CANVAS_WIDTH - 1, $totalHeight - 1, $bgColor);

        // 4. 渲染各区域
        $this->renderTitleBar($img);
        $this->renderHeader($img, $killData);
        $this->renderStatsBar($img, $killData);

        $contentY = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT + self::STATS_BAR_HEIGHT;
        $this->renderAttackerList($img, $attackers, $contentY, $victim['damage_taken'] ?? 0);
        $this->renderItemList($img, $itemsBySlot, $contentY);

        // 渲染中间分隔线
        $dividerX = self::LEFT_COL_WIDTH + self::PADDING;
        $dividerColor = $this->allocColor($img, self::COLOR_BORDER);
        imageline($img, $dividerX, $contentY, $dividerX, $contentY + $contentHeight, $dividerColor);

        $this->renderFooter($img, $killData, $totalHeight);

        // 5. 输出
        imagepng($img, $cachedPath, 6);
        imagedestroy($img);

        return $cachedPath;
    }

    // ================================================================
    //  标题栏 - "击毁报告" (仿游戏窗口标题)
    // ================================================================

    protected function renderTitleBar($img): void
    {
        // 标题栏背景 (略深)
        $titleBg = $this->allocColor($img, [18, 22, 35]);
        imagefilledrectangle($img, 0, 0, self::CANVAS_WIDTH - 1, self::TITLE_BAR_HEIGHT - 1, $titleBg);

        // 底部细线
        $borderColor = $this->allocColor($img, self::COLOR_BORDER);
        imageline($img, 0, self::TITLE_BAR_HEIGHT - 1, self::CANVAS_WIDTH - 1, self::TITLE_BAR_HEIGHT - 1, $borderColor);

        // 左侧图标占位 (小方块)
        $iconColor = $this->allocColor($img, [60, 70, 95]);
        imagefilledrectangle($img, 10, 7, 26, 23, $iconColor);

        // 标题文字
        $this->drawText($img, '击毁报告', 34, 6,
            self::FONT_SIZE_HEADER, self::COLOR_TEXT_PRIMARY, true);
    }

    // ================================================================
    //  顶部区域 - 角色头像 + 舰船渲染 + 基本信息
    // ================================================================

    protected function renderHeader($img, array $data): void
    {
        $p = self::PADDING;
        $offsetY = self::TITLE_BAR_HEIGHT; // 标题栏下方开始

        // 面板背景
        $panelColor = $this->allocColor($img, self::COLOR_PANEL);
        imagefilledrectangle($img, 0, $offsetY, self::CANVAS_WIDTH - 1, $offsetY + self::HEADER_HEIGHT - 1, $panelColor);

        // 底部边框
        $borderColor = $this->allocColor($img, self::COLOR_BORDER);
        imageline($img, 0, $offsetY + self::HEADER_HEIGHT - 1,
            self::CANVAS_WIDTH - 1, $offsetY + self::HEADER_HEIGHT - 1, $borderColor);

        $victim = $data['victim'] ?? [];

        // ---- 角色头像 (左侧) ----
        $portraitX = $p;
        $portraitY = $offsetY + (self::HEADER_HEIGHT - self::PORTRAIT_SIZE) / 2;
        $portrait = $this->fetchEveImage('characters', $victim['character_id'] ?? 0, 'portrait', self::PORTRAIT_SIZE);
        if ($portrait) {
            $this->drawImage($img, $portrait, $portraitX, $portraitY, self::PORTRAIT_SIZE, self::PORTRAIT_SIZE);
            imagedestroy($portrait);
        } else {
            $this->drawPlaceholder($img, $portraitX, $portraitY, self::PORTRAIT_SIZE, self::PORTRAIT_SIZE);
        }

        // 头像边框
        imagerectangle($img, $portraitX, $portraitY,
            $portraitX + self::PORTRAIT_SIZE - 1,
            $portraitY + self::PORTRAIT_SIZE - 1, $borderColor);

        // ---- 舰船渲染图 (右侧) ----
        $shipRenderW = 180;
        $shipRenderH = 150;
        $shipX = self::CANVAS_WIDTH - $shipRenderW - $p - 10;
        $shipY = $offsetY + (self::HEADER_HEIGHT - $shipRenderH) / 2;
        $shipImg = $this->fetchEveImage('types', $victim['ship_type_id'] ?? 0, 'render', 256);
        if ($shipImg) {
            $this->drawImage($img, $shipImg, $shipX, $shipY, $shipRenderW, $shipRenderH);
            imagedestroy($shipImg);
        }

        // ---- 文字信息 (中间区域) ----
        $textX = $portraitX + self::PORTRAIT_SIZE + $p;

        // 角色名 (大号，游戏内使用白/金色)
        $nameY = $portraitY + 2;
        $this->drawText($img, $victim['character_name'] ?? '未知', $textX, $nameY,
            self::FONT_SIZE_TITLE, self::COLOR_TEXT_HIGHLIGHT, true);

        // 军团图标 + 军团名
        $corpY = $nameY + 28;
        $corpIcon = $this->fetchEveImage('corporations', $victim['corporation_id'] ?? 0, 'logo', 32);
        if ($corpIcon) {
            $this->drawImage($img, $corpIcon, $textX, $corpY, 20, 20);
            imagedestroy($corpIcon);
        }
        // 军团名 (联盟名)
        $corpText = $victim['corporation_name'] ?? '';
        $this->drawText($img, $corpText, $textX + 24, $corpY + 2,
            self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY);

        // 联盟名 (如果有，另起一行)
        $allianceY = $corpY + 20;
        if ($victim['alliance_name'] ?? null) {
            $allianceIcon = $this->fetchEveImage('alliances', $victim['alliance_id'] ?? 0, 'logo', 32);
            if ($allianceIcon) {
                $this->drawImage($img, $allianceIcon, $textX, $allianceY, 20, 20);
                imagedestroy($allianceIcon);
            }
            $this->drawText($img, $victim['alliance_name'], $textX + 24, $allianceY + 2,
                self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY);
            $nextLineY = $allianceY + 22;
        } else {
            $nextLineY = $allianceY + 2;
        }

        // 舰船名称 + "(类型)"，参考游戏内格式如 "普罗维登斯级 (货舰)"
        $shipName = $victim['ship_name'] ?? '未知舰船';
        $this->drawText($img, $shipName, $textX, $nextLineY,
            self::FONT_SIZE_HEADER, self::COLOR_TEXT_PRIMARY, true);

        // 时间
        $timeY = $nextLineY + 22;
        $this->drawText($img, $data['kill_time'] ?? '', $textX, $timeY,
            self::FONT_SIZE_NORMAL, self::COLOR_TEXT_SECONDARY);

        // 地点: 星系 安等 < 星域
        $locY = $timeY + 20;
        $systemName = $data['solar_system_name'] ?? '未知';
        $secStatus = $data['system_sec'] ?? 0;
        $regionName = $data['region_name'] ?? '';

        $secColor = $this->getSecurityColor($secStatus);
        $secText = number_format($secStatus, 1);

        // 游戏内格式: "吉他 0.9 < 木本吕 < 伏尔戈"
        $this->drawText($img, $systemName, $textX, $locY,
            self::FONT_SIZE_NORMAL, self::COLOR_TEXT_BLUE);

        $sysWidth = $this->getTextWidth($systemName, self::FONT_SIZE_NORMAL);
        $this->drawText($img, " {$secText}", $textX + $sysWidth, $locY,
            self::FONT_SIZE_NORMAL, $secColor);

        if ($regionName) {
            $secWidth = $this->getTextWidth(" {$secText}", self::FONT_SIZE_NORMAL);
            $this->drawText($img, " < {$regionName}", $textX + $sysWidth + $secWidth, $locY,
                self::FONT_SIZE_NORMAL, self::COLOR_TEXT_SECONDARY);
        }
    }

    // ================================================================
    //  统计栏 - 参与者数 + 总伤害
    // ================================================================

    protected function renderStatsBar($img, array $data): void
    {
        $y = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT;
        $p = self::PADDING;

        // 背景
        $headerBg = $this->allocColor($img, self::COLOR_PANEL_HEADER);
        imagefilledrectangle($img, 0, $y, self::CANVAS_WIDTH - 1, $y + self::STATS_BAR_HEIGHT - 1, $headerBg);

        // 底部边框
        $borderColor = $this->allocColor($img, self::COLOR_BORDER);
        imageline($img, 0, $y + self::STATS_BAR_HEIGHT - 1,
            self::CANVAS_WIDTH - 1, $y + self::STATS_BAR_HEIGHT - 1, $borderColor);

        // 中间竖线分隔 (与内容区分隔线对齐)
        $dividerX = self::LEFT_COL_WIDTH + $p;
        imageline($img, $dividerX, $y, $dividerX, $y + self::STATS_BAR_HEIGHT - 1, $borderColor);

        $attackerCount = $data['attacker_count'] ?? count($data['attackers'] ?? []);
        $damageTaken = $data['victim']['damage_taken'] ?? 0;

        // ---- 左侧 ----
        // "进攻" 标签 (参考游戏内有 "进攻" / "支援" 标签)
        $this->drawText($img, '进攻', $p, $y + 4,
            self::FONT_SIZE_NORMAL, self::COLOR_TEXT_HIGHLIGHT, true);

        // 参与者数量
        $countText = "参与者 ({$attackerCount})";
        $this->drawText($img, $countText, $p + 50, $y + 5,
            self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY);

        // 总伤害 (红色)
        $damageText = "共受到伤害：" . number_format($damageTaken);
        $this->drawText($img, $damageText, $p + 50 + $this->getTextWidth($countText, self::FONT_SIZE_SMALL) + 15, $y + 5,
            self::FONT_SIZE_SMALL, self::COLOR_TEXT_RED);

        // ---- 右侧 ----
        $rightLabel = "装备与明细";
        $rightX = self::LEFT_COL_WIDTH + $p + 10;
        $this->drawText($img, $rightLabel, $rightX, $y + 8,
            self::FONT_SIZE_HEADER, self::COLOR_TEXT_PRIMARY, true);
    }

    // ================================================================
    //  左栏 - 参与者列表
    // ================================================================

    protected function renderAttackerList($img, array $attackers, int $startY, int $totalDamage): void
    {
        $p = self::PADDING;
        $x = $p;
        $y = $startY + 6;
        $rowHeight = self::ATTACKER_ICON_SIZE + 12;

        // 找出最后一击者和最大伤害者
        $finalBlowIdx = -1;
        $maxDmgIdx = -1;
        $maxDmg = 0;
        foreach ($attackers as $i => $atk) {
            if ($atk['final_blow'] ?? false) $finalBlowIdx = $i;
            if (($atk['damage_done'] ?? 0) > $maxDmg) {
                $maxDmg = $atk['damage_done'];
                $maxDmgIdx = $i;
            }
        }

        // 游戏内有 "最后一击" 和 "造成伤害最多" 两个分类标签
        $drawnFinalBlow = false;
        $drawnMaxDmg = false;

        foreach ($attackers as $i => $atk) {
            // ---- 分类标签 (仿游戏内布局) ----
            if ($i === 0 && $finalBlowIdx >= 0) {
                // "最后一击" 标签
                $this->drawText($img, '最后一击', $x, $y,
                    self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY, true);
                $y += 18;
            }

            // 当最后一击者后面开始显示"造成伤害最多"
            if ($i > 0 && $i === 1 && !$drawnMaxDmg) {
                $this->drawText($img, '造成伤害最多', $x, $y,
                    self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY, true);
                $y += 18;
                $drawnMaxDmg = true;
            }

            $iconX = $x;
            $iconY = $y;

            // ---- 角色头像 ----
            $charId = $atk['character_id'] ?? 0;
            $portrait = null;
            if ($charId > 0) {
                $portrait = $this->fetchEveImage('characters', $charId, 'portrait', 64);
            }
            if ($portrait) {
                $this->drawImage($img, $portrait, $iconX, $iconY, self::ATTACKER_ICON_SIZE, self::ATTACKER_ICON_SIZE);
                imagedestroy($portrait);
            } else {
                $this->drawPlaceholder($img, $iconX, $iconY, self::ATTACKER_ICON_SIZE, self::ATTACKER_ICON_SIZE);
            }

            // 舰船小图标 (右下角叠加，参考游戏内布局)
            $shipId = $atk['ship_type_id'] ?? 0;
            if ($shipId > 0) {
                $shipIcon = $this->fetchEveImage('types', $shipId, 'icon', 64);
                if ($shipIcon) {
                    $shipIconSize = 18;
                    $this->drawImage($img, $shipIcon,
                        $iconX + self::ATTACKER_ICON_SIZE - $shipIconSize,
                        $iconY + self::ATTACKER_ICON_SIZE - $shipIconSize,
                        $shipIconSize, $shipIconSize);
                    imagedestroy($shipIcon);
                }
            }

            // 最后一击标记 (金色边框)
            if ($atk['final_blow'] ?? false) {
                $fbColor = $this->allocColor($img, self::COLOR_FINAL_BLOW);
                imagerectangle($img, $iconX, $iconY,
                    $iconX + self::ATTACKER_ICON_SIZE - 1,
                    $iconY + self::ATTACKER_ICON_SIZE - 1, $fbColor);
            }

            // ---- 文字区域 ----
            $textX = $iconX + self::ATTACKER_ICON_SIZE + 8;

            // 第一行: 角色名
            $charName = $atk['character_name'] ?? $atk['corporation_name'] ?? '未知';
            $nameColor = ($atk['final_blow'] ?? false) ? self::COLOR_FINAL_BLOW : self::COLOR_TEXT_PRIMARY;
            $this->drawText($img, $charName, $textX, $iconY - 1,
                self::FONT_SIZE_NORMAL, $nameColor);

            // 第二行: 军团名
            $corpName = $atk['corporation_name'] ?? '';
            $this->drawText($img, $corpName, $textX, $iconY + 15,
                self::FONT_SIZE_TINY, self::COLOR_TEXT_SECONDARY);

            // 第三行: 联盟名 (如果有)
            if ($atk['alliance_name'] ?? null) {
                $this->drawText($img, $atk['alliance_name'], $textX, $iconY + 27,
                    self::FONT_SIZE_TINY, [100, 110, 130]);
            }

            // ---- 右侧: 伤害数值 + 百分比 ----
            $dmg = $atk['damage_done'] ?? 0;
            $pct = $totalDamage > 0 ? round($dmg / $totalDamage * 100, 1) : 0;
            $dmgText = number_format($dmg) . " ({$pct}%)";
            $dmgWidth = $this->getTextWidth($dmgText, self::FONT_SIZE_SMALL);
            $dmgX = self::LEFT_COL_WIDTH - $p - $dmgWidth;
            $this->drawText($img, $dmgText, $dmgX, $iconY + 8,
                self::FONT_SIZE_SMALL, self::COLOR_TEXT_SECONDARY);

            $y += $rowHeight;

            // 分隔线
            if ($i < count($attackers) - 1) {
                $divColor = $this->allocColor($img, self::COLOR_DIVIDER);
                imageline($img, $x, $y - 5, self::LEFT_COL_WIDTH - $p, $y - 5, $divColor);
            }
        }
    }

    // ================================================================
    //  右栏 - 装备与明细 (按槽位分组)
    // ================================================================

    protected function renderItemList($img, array $itemsBySlot, int $startY): void
    {
        $p = self::PADDING;
        $baseX = self::LEFT_COL_WIDTH + $p + 10;
        $y = $startY + 8;
        $colWidth = self::RIGHT_COL_WIDTH - $p * 2;

        // 槽位图标映射 (简单的色块标识，参考游戏内UI)
        $slotColors = [
            '高槽'   => [200, 170, 80],    // 金黄
            '中槽'   => [80, 160, 200],     // 天蓝
            '低槽'   => [80, 200, 120],     // 翠绿
            '改装件'  => [160, 120, 200],    // 紫色
            '子系统'  => [200, 130, 80],     // 橙色
            '无人机舱' => [140, 180, 160],   // 灰绿
            '弹药舱'  => [200, 160, 100],    // 土黄
            '货柜舱'  => [160, 140, 120],    // 棕色
            '其他'   => [130, 130, 140],     // 灰色
        ];

        foreach ($itemsBySlot as $slotName => $items) {
            if (empty($items)) continue;

            // ---- 槽位标题栏 ----
            $headerBg = $this->allocColor($img, self::COLOR_SLOT_HEADER);
            imagefilledrectangle($img, $baseX, $y, $baseX + $colWidth, $y + 22, $headerBg);

            // 槽位颜色标识条
            $slotColor = $slotColors[$slotName] ?? [130, 130, 140];
            $indicatorColor = $this->allocColor($img, $slotColor);
            imagefilledrectangle($img, $baseX, $y, $baseX + 4, $y + 22, $indicatorColor);

            // 槽位名称
            $this->drawText($img, $slotName, $baseX + 12, $y + 3,
                self::FONT_SIZE_NORMAL, self::COLOR_TEXT_PRIMARY, true);

            $y += 26;

            // ---- 物品列表 ----
            foreach ($items as $item) {
                $itemX = $baseX + 4;
                $itemY = $y;

                // 物品图标
                $itemIcon = $this->fetchEveImage('types', $item['item_type_id'] ?? 0, 'icon', 64);
                if ($itemIcon) {
                    $this->drawImage($img, $itemIcon, $itemX, $itemY, self::ITEM_ICON_SIZE, self::ITEM_ICON_SIZE);
                    imagedestroy($itemIcon);
                } else {
                    $this->drawPlaceholder($img, $itemX, $itemY, self::ITEM_ICON_SIZE, self::ITEM_ICON_SIZE);
                }

                // 状态指示 (销毁=红色半透明覆盖, 坠落=绿色半透明覆盖)
                $status = $item['status'] ?? 'destroyed';
                if ($status === 'destroyed') {
                    $this->drawOverlay($img, $itemX, $itemY, self::ITEM_ICON_SIZE, self::ITEM_ICON_SIZE, [180, 50, 50, 60]);
                }

                // 物品名称
                $nameX = $itemX + self::ITEM_ICON_SIZE + 8;
                $itemNameColor = ($status === 'destroyed') ? self::COLOR_TEXT_RED : self::COLOR_TEXT_PRIMARY;
                $itemName = $item['item_name'] ?? '未知物品';
                // 截断过长的名称
                if (mb_strlen($itemName) > 20) {
                    $itemName = mb_substr($itemName, 0, 19) . '…';
                }
                $this->drawText($img, $itemName, $nameX, $itemY + 2,
                    self::FONT_SIZE_SMALL, $itemNameColor);

                // 价格 (第二行)
                if (isset($item['total_price']) && $item['total_price'] > 0) {
                    $priceText = $this->formatIsk($item['total_price']);
                    $this->drawText($img, $priceText, $nameX, $itemY + 17,
                        self::FONT_SIZE_TINY, self::COLOR_TEXT_SECONDARY);
                }

                // 数量 (右侧)
                $qty = $item['quantity'] ?? 1;
                if ($qty > 1) {
                    $qtyText = "x{$qty}";
                    $qtyWidth = $this->getTextWidth($qtyText, self::FONT_SIZE_NORMAL);
                    $qtyX = $baseX + $colWidth - $qtyWidth;
                    $this->drawText($img, $qtyText, $qtyX, $itemY + 6,
                        self::FONT_SIZE_NORMAL, self::COLOR_TEXT_HIGHLIGHT);
                }

                // 数量为1时也显示
                if ($qty == 1) {
                    $qtyText = "1";
                    $qtyWidth = $this->getTextWidth($qtyText, self::FONT_SIZE_NORMAL);
                    $qtyX = $baseX + $colWidth - $qtyWidth;
                    $this->drawText($img, $qtyText, $qtyX, $itemY + 6,
                        self::FONT_SIZE_NORMAL, self::COLOR_TEXT_SECONDARY);
                }

                $y += self::ITEM_ICON_SIZE + 6;
            }

            $y += 6; // 槽位间距
        }
    }

    // ================================================================
    //  底部 - 总价值
    // ================================================================

    protected function renderFooter($img, array $data, int $totalHeight): void
    {
        $p = self::PADDING;
        $y = $totalHeight - self::FOOTER_HEIGHT;

        // 背景
        $panelColor = $this->allocColor($img, self::COLOR_PANEL);
        imagefilledrectangle($img, 0, $y, self::CANVAS_WIDTH - 1, $totalHeight - 1, $panelColor);

        // 顶部边框
        $borderColor = $this->allocColor($img, self::COLOR_BORDER);
        imageline($img, 0, $y, self::CANVAS_WIDTH - 1, $y, $borderColor);

        // 计算总价值
        $totalValue = $this->calculateTotalValue($data['items_by_slot'] ?? []);

        // 游戏内格式: "总价值：5,113,425,892 星币"
        $label = "总价值：";
        $valueText = number_format($totalValue, 0) . " 星币";

        // 居中显示
        $labelWidth = $this->getTextWidth($label, self::FONT_SIZE_VALUE);
        $valueWidth = $this->getTextWidth($valueText, self::FONT_SIZE_VALUE);
        $totalTextWidth = $labelWidth + $valueWidth;
        $startX = (self::CANVAS_WIDTH - $totalTextWidth) / 2;

        $this->drawText($img, $label, $startX, $y + 14,
            self::FONT_SIZE_VALUE, self::COLOR_TEXT_SECONDARY, true);

        $this->drawText($img, $valueText, $startX + $labelWidth, $y + 14,
            self::FONT_SIZE_VALUE, self::COLOR_TEXT_HIGHLIGHT, true);

        // Kill ID (右下角小字)
        $killIdText = "Kill ID: " . ($data['kill_id'] ?? 'N/A');
        $killIdWidth = $this->getTextWidth($killIdText, self::FONT_SIZE_TINY);
        $this->drawText($img, $killIdText, self::CANVAS_WIDTH - $p - $killIdWidth, $y + 30,
            self::FONT_SIZE_TINY, [80, 90, 110]);
    }

    // ================================================================
    //  高度计算
    // ================================================================

    protected function calculateAttackerListHeight(array $attackers): int
    {
        $rowHeight = self::ATTACKER_ICON_SIZE + 12;
        $labelHeight = 18 * 2; // "最后一击" + "造成伤害最多" 两个标签
        return count($attackers) * $rowHeight + $labelHeight + 16;
    }

    protected function calculateItemListHeight(array $itemsBySlot): int
    {
        $height = 16;
        foreach ($itemsBySlot as $slotName => $items) {
            if (empty($items)) continue;
            $height += 26; // 槽位标题
            $height += count($items) * (self::ITEM_ICON_SIZE + 6); // 物品行
            $height += 6;  // 槽位间距
        }
        return $height;
    }

    // ================================================================
    //  EVE 图片获取 (带缓存)
    // ================================================================

    /**
     * 获取 EVE 图片资源
     *
     * @param string $category characters|corporations|alliances|types
     * @param int $id 实体ID
     * @param string $variant portrait|logo|icon|render
     * @param int $size 图片尺寸
     * @return \GdImage|null
     */
    protected function fetchEveImage(string $category, int $id, string $variant, int $size): ?\GdImage
    {
        if ($id <= 0) return null;

        $cacheKey = "eve_img_{$category}_{$id}_{$variant}_{$size}";
        $localPath = $this->cachePath . "/img_{$category}_{$id}_{$variant}_{$size}.png";

        // 本地文件缓存
        if (file_exists($localPath) && (time() - filemtime($localPath)) < 86400) {
            $loaded = @imagecreatefrompng($localPath);
            if ($loaded) return $loaded;
            // PNG失败，尝试JPEG
            $loaded = @imagecreatefromjpeg($localPath);
            if ($loaded) return $loaded;
        }

        try {
            $url = self::EVE_IMAGE_BASE . "/{$category}/{$id}/{$variant}?size={$size}";
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $body = $response->body();
                file_put_contents($localPath, $body);

                $loaded = @imagecreatefromstring($body);
                if ($loaded) return $loaded;
            }
        } catch (\Throwable $e) {
            Log::warning("KM Image: Failed to fetch {$category}/{$id}/{$variant}", [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    // ================================================================
    //  绘图辅助方法
    // ================================================================

    /**
     * 绘制文字
     */
    protected function drawText($img, string $text, int $x, int $y, int $size, array $color, bool $bold = false): void
    {
        $fontFile = $bold ? $this->fontBoldPath : $this->fontPath;

        // 如果字体文件不存在，回退到内置字体
        if (!file_exists($fontFile)) {
            $fontFile = $this->fontPath;
        }

        $textColor = $this->allocColor($img, $color);

        if (file_exists($fontFile)) {
            // TTF 字体渲染
            imagettftext($img, $size, 0, $x, $y + $size, $textColor, $fontFile, $text);
        } else {
            // 无字体文件时使用内置字体（仅英文）
            $builtinFont = min(5, max(1, intdiv($size, 4)));
            imagestring($img, $builtinFont, $x, $y, $text, $textColor);
        }
    }

    /**
     * 获取文字宽度
     */
    protected function getTextWidth(string $text, int $size, bool $bold = false): int
    {
        $fontFile = $bold ? $this->fontBoldPath : $this->fontPath;
        if (!file_exists($fontFile)) {
            $fontFile = $this->fontPath;
        }

        if (file_exists($fontFile)) {
            $bbox = imagettfbbox($size, 0, $fontFile, $text);
            return abs($bbox[2] - $bbox[0]);
        }

        // 回退估算
        return strlen($text) * ($size * 0.6);
    }

    /**
     * 将图片绘制到画布上（缩放）
     */
    protected function drawImage($img, $srcImg, int $dstX, int $dstY, int $dstW, int $dstH): void
    {
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);
        imagecopyresampled($img, $srcImg, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
    }

    /**
     * 绘制占位符矩形
     */
    protected function drawPlaceholder($img, int $x, int $y, int $w, int $h): void
    {
        $bgColor = $this->allocColor($img, [35, 40, 55]);
        $borderColor = $this->allocColor($img, self::COLOR_BORDER);
        imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $bgColor);
        imagerectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $borderColor);
    }

    /**
     * 绘制半透明覆盖层 (用于销毁/坠落状态标识)
     */
    protected function drawOverlay($img, int $x, int $y, int $w, int $h, array $rgba): void
    {
        $overlay = imagecreatetruecolor($w, $h);
        imagesavealpha($overlay, true);
        imagealphablending($overlay, false);
        $color = imagecolorallocatealpha($overlay, $rgba[0], $rgba[1], $rgba[2], $rgba[3] ?? 0);
        imagefilledrectangle($overlay, 0, 0, $w - 1, $h - 1, $color);
        imagealphablending($img, true);
        imagecopy($img, $overlay, $x, $y, 0, 0, $w, $h);
        imagedestroy($overlay);
    }

    /**
     * 分配颜色
     */
    protected function allocColor($img, array $rgb): int
    {
        if (count($rgb) >= 4) {
            return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $rgb[3]);
        }
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * 安全等级对应颜色
     */
    protected function getSecurityColor(float $sec): array
    {
        if ($sec >= 0.9) return [0, 255, 0];
        if ($sec >= 0.7) return [0, 200, 0];
        if ($sec >= 0.5) return [255, 255, 0];
        if ($sec >= 0.3) return [255, 160, 0];
        if ($sec >= 0.1) return [255, 80, 0];
        if ($sec > 0.0) return [255, 40, 0];
        return [255, 0, 0]; // 0.0 及以下
    }

    /**
     * 格式化 ISK 金额
     */
    protected function formatIsk(float $amount): string
    {
        if ($amount >= 1e12) {
            return number_format($amount / 1e12, 2) . 'T';
        }
        if ($amount >= 1e9) {
            return number_format($amount / 1e9, 2) . 'B';
        }
        if ($amount >= 1e6) {
            return number_format($amount / 1e6, 2) . 'M';
        }
        if ($amount >= 1e3) {
            return number_format($amount / 1e3, 1) . 'K';
        }
        return number_format($amount, 0);
    }

    /**
     * 计算物品总价值
     */
    protected function calculateTotalValue(array $itemsBySlot): float
    {
        $total = 0;
        foreach ($itemsBySlot as $items) {
            foreach ($items as $item) {
                $total += $item['total_price'] ?? 0;
            }
        }
        return $total;
    }

    /**
     * 清理过期缓存图片 (超过7天)
     */
    public function cleanCache(int $maxAgeDays = 7): int
    {
        $count = 0;
        $files = glob($this->cachePath . '/km_*.png');
        $maxAge = $maxAgeDays * 86400;

        foreach ($files as $file) {
            if ((time() - filemtime($file)) > $maxAge) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
