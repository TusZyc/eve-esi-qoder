<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
 * [Claude Code] 2026-04-05 (全面重构)
 *
 * 布局 (900px 宽):
 * ┌─────────────────────────────────────────────────────────────┐
 * │  标题栏: "击毁报告"                                          │ 32px
 * ├─────────────────────────────────────────────────────────────┤
 * │  头部: [头像128] [舰船渲染160] [角色名/军团联盟/舰船/位置]   │ 176px
 * ├─────────────────────────────────────────────────────────────┤
 * │  统计栏: 参与者(N) | 共受到伤害:X     |  装备与明细          │ 48px
 * ├───────────────────┬─────────────────────────────────────────┤
 * │  左栏 (参与者)    │  右栏 (装备明细)                        │
 * │  310px            │  576px                                   │
 * ├───────────────────┴─────────────────────────────────────────┤
 * │  底部: 总价值                                                │ 50px
 * └─────────────────────────────────────────────────────────────┘
 */
class KillmailImageService
{
    // ── 画布常量 ─────────────────────────────────────────────────────────
    const CANVAS_WIDTH     = 900;
    const PADDING          = 14;
    const TITLE_BAR_HEIGHT = 32;
    const HEADER_HEIGHT    = 192;
    const STATS_BAR_HEIGHT = 68;
    const FOOTER_HEIGHT    = 70;

    // 左栏（参与者）比右栏（装备）窄
    const LEFT_COL_WIDTH   = 360;
    // RIGHT_COL = CANVAS_WIDTH - LEFT_COL_WIDTH - 1

    // 图标尺寸
    const PORTRAIT_SIZE    = 160;   // 头部受害者头像（与舰船渲染同尺寸）
    const SHIP_RENDER_W    = 160;   // 头部舰船渲染宽
    const ATK_PORTRAIT     = 38;    // 攻击者头像
    const ATK_ICON         = 22;    // 攻击者舰船/武器图标
    const ITEM_ICON        = 32;    // 装备图标
    const ATK_ROW_H        = 54;    // 攻击者行高

    // ── 颜色 (纯黑背景主题) ───────────────────────────────────────────────
    const C_BG          = [0,   0,   0  ];   // 纯黑背景
    const C_PANEL       = [10,  12,  18 ];   // 面板背景
    const C_PANEL_HDR   = [16,  20,  30 ];   // 面板标题背景
    const C_SLOT_BAR    = [32,  38,  56 ];   // 槽位标题条
    const C_BORDER      = [45,  55,  75 ];   // 边框
    const C_DIVIDER     = [28,  34,  50 ];   // 分隔线
    const C_WHITE       = [220, 225, 235];   // 主文字
    const C_GREY        = [120, 132, 150];   // 次要文字
    const C_GOLD        = [255, 200,  50];   // 高亮金色
    const C_BLUE        = [ 80, 155, 255];   // 链接蓝
    const C_RED         = [210,  55,  55];   // 伤害红
    const C_GREEN       = [ 55, 190,  90];   // 坠落绿
    const C_FINAL_BLOW  = [200, 160,  50];   // 最后一击金
    const C_LIGHT_BLUE  = [140, 200, 255];   // 总掉落淡蓝
    const C_DROPPED_BG  = [  8,  35,  12];   // 坠落物品行背景
    const C_DESTROYED_BG= [ 25,  10,  10];   // 已销毁物品行背景

    // ── 字体大小 ─────────────────────────────────────────────────────────
    const FS_TITLE  = 17;
    const FS_LARGE  = 14;
    const FS_NORMAL = 12;
    const FS_SMALL  = 10;
    const FS_TINY   = 9;

    // 国服图片服务器 (image.evepc.163.com)
    // URL 格式: /Character/{id}_{size}.jpg  /Render/{id}_{size}.png
    //           /Type/{id}_{size}.png        /Corporation/{id}_{size}.png
    //           /Alliance/{id}_{size}.png
    const EVE_IMAGE_BASE = 'https://image.evepc.163.com';

    protected string $fontPath;
    protected string $fontBoldPath;
    protected string $cachePath;
    protected ?array $localItems = null;

    public function __construct()
    {
        $this->fontPath     = storage_path('fonts/NotoSansSC-Regular.ttf');
        $this->fontBoldPath = storage_path('fonts/NotoSansSC-Bold.ttf');
        $this->cachePath    = storage_path('app/km-images');

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  生成入口
    // ─────────────────────────────────────────────────────────────────────

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
        $leftH    = $this->calcAttackerH($attackers);
        $rightH   = $this->calcItemH($itemsBySlot);
        $contentH = max($leftH, $rightH) + self::PADDING * 2;

        $totalH = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT
                + self::STATS_BAR_HEIGHT + $contentH + self::FOOTER_HEIGHT;

        // 创建画布
        $img = imagecreatetruecolor(self::CANVAS_WIDTH, $totalH);
        imagesavealpha($img, true);
        imagealphablending($img, true);

        // 纯黑背景
        $bg = $this->c($img, self::C_BG);
        imagefilledrectangle($img, 0, 0, self::CANVAS_WIDTH - 1, $totalH - 1, $bg);

        // 渲染各区域
        $this->drawTitleBar($img, $killData);
        $this->drawHeader($img, $killData);
        $this->drawStatsBar($img, $killData);

        $contentY = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT + self::STATS_BAR_HEIGHT;
        $this->drawAttackers($img, $attackers, $contentY, (int)($victim['damage_taken'] ?? 0));
        $this->drawItems($img, $itemsBySlot, $contentY);
        $this->drawFooter($img, $killData, $totalH);

        imagepng($img, $cachedPath, 5);
        imagedestroy($img);

        return $cachedPath;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  标题栏
    // ─────────────────────────────────────────────────────────────────────

    protected function drawTitleBar($img, array $data): void
    {
        $bar = $this->c($img, [14, 17, 26]);
        imagefilledrectangle($img, 0, 0, self::CANVAS_WIDTH - 1, self::TITLE_BAR_HEIGHT - 1, $bar);

        $border = $this->c($img, self::C_BORDER);
        imageline($img, 0, self::TITLE_BAR_HEIGHT - 1, self::CANVAS_WIDTH - 1, self::TITLE_BAR_HEIGHT - 1, $border);

        // 左侧小图标
        $ico = $this->c($img, [55, 65, 90]);
        imagefilledrectangle($img, 10, 8, 24, 22, $ico);

        $this->txt($img, '击毁报告', 32, 6, self::FS_LARGE, self::C_WHITE, true);

        // 右侧归属与 Kill ID
        $killId = $data['kill_id'] ?? '';
        $attribution = "由 Tus Esi System生成  Kill ID：{$killId}";
        $w = $this->tw($attribution, self::FS_SMALL);
        $this->txt($img, $attribution, self::CANVAS_WIDTH - self::PADDING - $w, 8, self::FS_SMALL, self::C_GREY);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  头部: [头像] [舰船渲染] [文字信息]
    //  布局顺序: 左→右 = 头像 | 舰船渲染 | 角色名+军团联盟+舰船+位置
    // ─────────────────────────────────────────────────────────────────────

    protected function drawHeader($img, array $data): void
    {
        $p  = self::PADDING;
        $y0 = self::TITLE_BAR_HEIGHT;

        // 面板背景
        $panelBg = $this->c($img, self::C_PANEL);
        imagefilledrectangle($img, 0, $y0, self::CANVAS_WIDTH - 1, $y0 + self::HEADER_HEIGHT - 1, $panelBg);

        $border = $this->c($img, self::C_BORDER);
        imageline($img, 0, $y0 + self::HEADER_HEIGHT - 1, self::CANVAS_WIDTH - 1, $y0 + self::HEADER_HEIGHT - 1, $border);

        $victim = $data['victim'] ?? [];

        // ── 1. 角色头像 (最左) ────────────────────────────────────────
        $portraitX = $p;
        $portraitY = (int)($y0 + (self::HEADER_HEIGHT - self::PORTRAIT_SIZE) / 2);

        $portrait = $this->fetchImg('characters', (int)($victim['character_id'] ?? 0), 'portrait', 256);
        if ($portrait) {
            $this->scaled($img, $portrait, $portraitX, $portraitY, self::PORTRAIT_SIZE, self::PORTRAIT_SIZE);
            imagedestroy($portrait);
        } else {
            $this->placeholder($img, $portraitX, $portraitY, self::PORTRAIT_SIZE, self::PORTRAIT_SIZE);
        }
        imagerectangle($img, $portraitX, $portraitY,
            $portraitX + self::PORTRAIT_SIZE - 1, $portraitY + self::PORTRAIT_SIZE - 1, $border);

        // ── 2. 舰船渲染图 (紧接头像右侧) ────────────────────────────
        $shipW = self::SHIP_RENDER_W;
        $shipH = self::PORTRAIT_SIZE;  // 与头像等高
        $shipX = $portraitX + self::PORTRAIT_SIZE + 8;
        $shipY = (int)($y0 + (self::HEADER_HEIGHT - $shipH) / 2);

        $shipImg = $this->fetchImg('types', (int)($victim['ship_type_id'] ?? 0), 'render', 256);
        if ($shipImg) {
            $this->scaled($img, $shipImg, $shipX, $shipY, $shipW, $shipH);
            imagedestroy($shipImg);
        }

        // ── 3. 文字信息 (舰船渲染图右侧) ────────────────────────────
        $textX = $shipX + $shipW + 14;
        $textY = $portraitY + 2;

        // 角色名 (大号白色)
        $charName = $victim['character_name'] ?? '未知';
        $this->txt($img, $charName, $textX, $textY, self::FS_TITLE, self::C_WHITE, true);

        // 军团 + 联盟 左右排列: [军团图标40] [联盟图标40]  军团名\n联盟名
        $orgY     = $textY + 24;
        $corpId   = (int)($victim['corporation_id'] ?? 0);
        $corpNm   = $victim['corporation_name'] ?? '';
        $aliId    = (int)($victim['alliance_id'] ?? 0);
        $aliNm    = $victim['alliance_name'] ?? '';
        $icoSize  = 40;
        $icoGap   = 6;

        // 军团图标
        if ($corpId > 0) {
            $corpIco = $this->fetchImg('corporations', $corpId, 'logo', 64);
            if ($corpIco) {
                $this->scaled($img, $corpIco, $textX, $orgY, $icoSize, $icoSize);
                imagedestroy($corpIco);
            } else {
                $this->placeholder($img, $textX, $orgY, $icoSize, $icoSize);
            }
        }

        // 联盟图标 (紧接军团图标右边)
        $aliIcoX = $corpId > 0 ? $textX + $icoSize + $icoGap : $textX;
        if ($aliId > 0) {
            $aliIco = $this->fetchImg('alliances', $aliId, 'logo', 64);
            if ($aliIco) {
                $this->scaled($img, $aliIco, $aliIcoX, $orgY, $icoSize, $icoSize);
                imagedestroy($aliIco);
            } else {
                $this->placeholder($img, $aliIcoX, $orgY, $icoSize, $icoSize);
            }
        }

        // 名字列: 图标右侧，上下排列
        $nameColX = $aliId > 0 ? $aliIcoX + $icoSize + 8 : ($corpId > 0 ? $textX + $icoSize + 8 : $textX);
        $maxNameW = self::CANVAS_WIDTH - $nameColX - self::PADDING;
        if ($corpNm) {
            $this->txtTrunc($img, $corpNm, $nameColX, $orgY + 2, self::FS_SMALL, self::C_WHITE, $maxNameW);
        }
        if ($aliNm) {
            $this->txtTrunc($img, $aliNm, $nameColX, $orgY + 18, self::FS_SMALL, self::C_GREY, $maxNameW);
        }

        // 舰船名称 + "(船体：XXX ISK)" + "(舰船类型)"
        $shipRowY  = $orgY + $icoSize + 6;
        $shipName  = $victim['ship_name'] ?? '未知舰船';
        $shipGroup = $this->getItemGroup((int)($victim['ship_type_id'] ?? 0));
        $hullPrice = $this->getHullLowestSellPrice((int)($victim['ship_type_id'] ?? 0));

        $shipLabel = $shipName;
        if ($shipGroup) $shipLabel .= "（{$shipGroup}）";
        if ($hullPrice !== null) $shipLabel .= '（船体：' . number_format($hullPrice, 0) . ' ISK）';
        $this->txt($img, $shipLabel, $textX, $shipRowY, self::FS_LARGE, self::C_WHITE, true);

        // 时间 (舰船名与位置之间)
        $timeY    = $shipRowY + 22;
        $killTime = $data['kill_time'] ?? '';
        if ($killTime) {
            $this->txt($img, $killTime, $textX, $timeY, self::FS_NORMAL, self::C_GREY);
        }

        // 位置: 星系（安等）< 星座 < 星域，全白，安等着色
        $locY          = $timeY + 20;
        $sysName       = $data['solar_system_name'] ?? '未知';
        $secStatus     = (float)($data['system_sec'] ?? 0);
        $secText       = number_format($secStatus, 1);
        $constellation = $data['constellation_name'] ?? '';
        $region        = $data['region_name'] ?? '';
        $secColor      = $this->secColor($secStatus);
        $maxLocW       = self::CANVAS_WIDTH - $textX - self::PADDING;

        $curX = $textX;
        $this->txt($img, $sysName, $curX, $locY, self::FS_NORMAL, self::C_WHITE);
        $curX += $this->tw($sysName, self::FS_NORMAL);
        $this->txt($img, '（', $curX, $locY, self::FS_NORMAL, self::C_WHITE);
        $curX += $this->tw('（', self::FS_NORMAL);
        $this->txt($img, $secText, $curX, $locY, self::FS_NORMAL, $secColor);
        $curX += $this->tw($secText, self::FS_NORMAL);
        $this->txt($img, '）', $curX, $locY, self::FS_NORMAL, self::C_WHITE);
        $curX += $this->tw('）', self::FS_NORMAL);
        if ($constellation) {
            $this->txt($img, ' < ' . $constellation, $curX, $locY, self::FS_NORMAL, self::C_WHITE);
            $curX += $this->tw(' < ' . $constellation, self::FS_NORMAL);
        }
        if ($region) {
            $this->txt($img, ' < ' . $region, $curX, $locY, self::FS_NORMAL, self::C_WHITE);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  统计栏
    //  左侧: 参与者(N) + 共受到伤害：X    右侧: 装备与明细
    // ─────────────────────────────────────────────────────────────────────

    protected function drawStatsBar($img, array $data): void
    {
        $y  = self::TITLE_BAR_HEIGHT + self::HEADER_HEIGHT;
        $p  = self::PADDING;
        $h  = self::STATS_BAR_HEIGHT;

        $bg = $this->c($img, self::C_PANEL_HDR);
        imagefilledrectangle($img, 0, $y, self::CANVAS_WIDTH - 1, $y + $h - 1, $bg);

        $border = $this->c($img, self::C_BORDER);
        imageline($img, 0, $y + $h - 1, self::CANVAS_WIDTH - 1, $y + $h - 1, $border);

        // 竖线分隔列
        $divX = self::LEFT_COL_WIDTH;
        imageline($img, $divX, $y, $divX, $y + $h - 1, $border);

        $attackerCount = count($data['attackers'] ?? []);
        $damageTaken   = (int)($data['victim']['damage_taken'] ?? 0);
        $supporters    = $data['supporters'] ?? [];
        $supportCount  = count($supporters);
        $repairDone    = (int)array_sum(array_column($supporters, 'repair_done'));

        // 左侧: 参与者数 (第一行) + 伤害 (第二行) + 支援 (第三行)
        $this->txt($img, "参与者 ({$attackerCount})", $p, $y + 4, self::FS_NORMAL, self::C_WHITE, true);
        $this->txt($img, '共受到伤害：' . number_format($damageTaken), $p, $y + 24, self::FS_SMALL, self::C_RED);
        if ($supportCount > 0) {
            $repairText = $repairDone > 0 ? "支援：{$supportCount} 人（修复 " . number_format($repairDone) . " HP）" : "支援：{$supportCount} 人";
            $this->txt($img, $repairText, $p, $y + 44, self::FS_SMALL, self::C_GREEN);
        }

        // 右侧: 装备与明细
        $rightX = self::LEFT_COL_WIDTH + $p;
        $this->txt($img, '装备与明细', $rightX, $y + 24, self::FS_LARGE, self::C_WHITE, true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  左栏: 攻击者列表
    //  顶部: 最后一击 | 造成伤害最多 (特殊标注)
    //  其余: 参与者列表
    //
    //  每行布局:
    //  [头像38×38] [舰船22×22] 角色名
    //              [武器22×22] 军团名
    //                          联盟名(如有)
    //                          伤害(X%)
    // ─────────────────────────────────────────────────────────────────────

    protected function drawAttackers($img, array $attackers, int $startY, int $totalDamage): void
    {
        if (empty($attackers)) return;

        $p  = self::PADDING;
        $y  = $startY + 8;

        // 找出最后一击 & 最高伤害
        $finalIdx = -1;
        $maxIdx   = -1;
        $maxDmg   = -1;
        foreach ($attackers as $i => $atk) {
            if ($atk['final_blow'] ?? false) $finalIdx = $i;
            if ((int)($atk['damage_done'] ?? 0) > $maxDmg) {
                $maxDmg = (int)($atk['damage_done'] ?? 0);
                $maxIdx = $i;
            }
        }

        // ── 特殊项: 最后一击 ────────────────────────────────────────
        if ($finalIdx >= 0) {
            $this->txt($img, '最后一击', $p, $y, self::FS_TINY, self::C_FINAL_BLOW, true);
            $y += 15;
            $this->drawAtkRow($img, $attackers[$finalIdx], $y, $totalDamage, true, false);
            $y += self::ATK_ROW_H;
        }

        // ── 特殊项: 造成伤害最多 (若与最后一击不同) ──────────────
        if ($maxIdx >= 0 && $maxIdx !== $finalIdx) {
            $this->txt($img, '造成伤害最多', $p, $y, self::FS_TINY, self::C_GOLD, true);
            $y += 15;
            $this->drawAtkRow($img, $attackers[$maxIdx], $y, $totalDamage, false, true);
            $y += self::ATK_ROW_H;
        }

        // ── 其余参与者 ───────────────────────────────────────────────
        foreach ($attackers as $i => $atk) {
            if ($i === $finalIdx || $i === $maxIdx) continue;

            $this->drawAtkRow($img, $atk, $y, $totalDamage, false, false);
            $y += self::ATK_ROW_H;
        }
    }

    /**
     * 绘制单行攻击者
     * 布局: [头像] [舰船图标/武器图标] [角色名/军团/联盟/伤害]
     */
    protected function drawAtkRow($img, array $atk, int $y, int $totalDamage, bool $isFinal, bool $isTopDmg): void
    {
        $p  = self::PADDING;
        $ps = self::ATK_PORTRAIT;  // 38
        $is = self::ATK_ICON;      // 22

        // ── 头像 ──────────────────────────────────────────────────────
        $px = $p;
        $py = $y + 2;

        $charId   = (int)($atk['character_id'] ?? 0);
        $portrait = $charId > 0 ? $this->fetchImg('characters', $charId, 'portrait', 64) : null;
        if ($portrait) {
            $this->scaled($img, $portrait, $px, $py, $ps, $ps);
            imagedestroy($portrait);
        } else {
            $this->placeholder($img, $px, $py, $ps, $ps);
        }

        // 最后一击: 金色边框
        if ($isFinal) {
            $fb = $this->c($img, self::C_FINAL_BLOW);
            imagerectangle($img, $px, $py, $px + $ps - 1, $py + $ps - 1, $fb);
        }

        // ── 上: 舰船图标 / 下: 武器图标 ──────────────────────────────
        $iconX = $px + $ps + 5;

        $shipId = (int)($atk['ship_type_id'] ?? 0);
        if ($shipId > 0) {
            $shipIco = $this->fetchImg('types', $shipId, 'icon', 32);
            if ($shipIco) {
                $this->scaled($img, $shipIco, $iconX, $py, $is, $is);
                imagedestroy($shipIco);
            } else {
                $this->placeholder($img, $iconX, $py, $is, $is);
            }
        } else {
            $this->placeholder($img, $iconX, $py, $is, $is);
        }

        $weaponId = (int)($atk['weapon_type_id'] ?? 0);
        $weapY    = $py + $is + 2;
        if ($weaponId > 0) {
            $weapIco = $this->fetchImg('types', $weaponId, 'icon', 32);
            if ($weapIco) {
                $this->scaled($img, $weapIco, $iconX, $weapY, $is, $is);
                imagedestroy($weapIco);
            } else {
                $this->placeholder($img, $iconX, $weapY, $is, $is);
            }
        } else {
            $this->placeholder($img, $iconX, $weapY, $is, $is);
        }

        // ── 文字: 角色名 / 军团 / 联盟 / 伤害 ──────────────────────
        $tx    = $iconX + $is + 6;
        $maxTW = self::LEFT_COL_WIDTH - $tx - 4;

        // 角色名
        $charName  = $atk['character_name'] ?? ($atk['corporation_name'] ?? '未知');
        $nameColor = $isFinal ? self::C_FINAL_BLOW : ($isTopDmg ? self::C_GOLD : self::C_WHITE);
        $this->txtTrunc($img, $charName, $tx, $py + 1, self::FS_NORMAL, $nameColor, $maxTW);

        // 军团名
        $corpNm = $atk['corporation_name'] ?? '';
        if ($corpNm) {
            $this->txtTrunc($img, $corpNm, $tx, $py + 15, self::FS_SMALL, self::C_WHITE, $maxTW);
        }

        // 联盟名
        $aliNm    = $atk['alliance_name'] ?? '';
        $dmgLineY = $py + 28;
        if ($aliNm) {
            $this->txtTrunc($img, $aliNm, $tx, $py + 27, self::FS_SMALL, self::C_WHITE, $maxTW);
            $dmgLineY = $py + 40;
        }

        // 伤害
        $dmg     = (int)($atk['damage_done'] ?? 0);
        $pct     = $totalDamage > 0 ? round($dmg / $totalDamage * 100, 1) : 0;
        $dmgText = number_format($dmg) . " ({$pct}%)";
        $this->txt($img, $dmgText, $tx, $dmgLineY, self::FS_TINY, self::C_WHITE);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  右栏: 装备明细（按槽位）
    //  掉落物品背景绿，已销毁背景红
    //  物品名称优先使用本地 eve_items.json
    // ─────────────────────────────────────────────────────────────────────

    protected function drawItems($img, array $itemsBySlot, int $startY): void
    {
        $p     = self::PADDING;
        $baseX = self::LEFT_COL_WIDTH + 1;
        $colW  = self::CANVAS_WIDTH - $baseX - $p;
        $y     = $startY + 8;

        // 槽位颜色标识
        $slotColors = [
            '高槽'    => [200, 170,  80],
            '中槽'    => [ 80, 155, 200],
            '低槽'    => [ 80, 200, 120],
            '改装件'  => [160, 120, 200],
            '子系统'  => [200, 130,  80],
            '无人机舱'=> [140, 180, 160],
            '弹药舱'  => [200, 155,  90],
            '货柜舱'  => [155, 140, 120],
            '其他'    => [130, 130, 140],
        ];

        foreach ($itemsBySlot as $slotName => $items) {
            if (empty($items)) continue;

            // 槽位标题栏
            $headerBg = $this->c($img, self::C_SLOT_BAR);
            imagefilledrectangle($img, $baseX, $y, $baseX + $colW, $y + 21, $headerBg);

            $slotRgb = $slotColors[$slotName] ?? [130, 130, 140];
            $slotInd = $this->c($img, $slotRgb);
            imagefilledrectangle($img, $baseX, $y, $baseX + 3, $y + 21, $slotInd);

            $this->txt($img, $slotName, $baseX + 10, $y + 2, self::FS_NORMAL, self::C_WHITE, true);
            $y += 25;

            // 物品列表
            foreach ($items as $item) {
                $status = $item['status'] ?? 'destroyed';
                $typeId = (int)($item['item_type_id'] ?? 0);
                $rowH   = self::ITEM_ICON + 6;

                // 行背景色: 掉落=深绿背景+绿色左侧条，销毁=无特殊背景
                if ($status === 'dropped') {
                    $rowBg = $this->c($img, self::C_DROPPED_BG);
                    imagefilledrectangle($img, $baseX, $y, $baseX + $colW, $y + $rowH - 1, $rowBg);
                    $strip = $this->c($img, self::C_GREEN);
                    imagefilledrectangle($img, $baseX, $y, $baseX + 2, $y + $rowH - 1, $strip);
                }

                // 物品图标
                $ico = $typeId > 0 ? $this->fetchImg('types', $typeId, 'icon', 64) : null;
                if ($ico) {
                    $this->scaled($img, $ico, $baseX + 4, $y + 2, self::ITEM_ICON, self::ITEM_ICON);
                    imagedestroy($ico);
                } else {
                    $this->placeholder($img, $baseX + 4, $y + 2, self::ITEM_ICON, self::ITEM_ICON);
                }

                // 物品名称 (优先本地数据)
                $nameX    = $baseX + 4 + self::ITEM_ICON + 6;
                $itemName = $this->resolveItemName($typeId, $item['item_name'] ?? '');

                // 数量 (右对齐)
                $qty     = (int)($item['quantity'] ?? 1);
                $qtyText = 'x' . number_format($qty);
                $qtyW    = $this->tw($qtyText, self::FS_SMALL);
                $qtyX    = $baseX + $colW - $qtyW - 4;
                $this->txt($img, $qtyText, $qtyX, $y + 12, self::FS_SMALL, self::C_WHITE);

                // 价格 (数量左边，右对齐)
                $priceRightX = $qtyX - 6;
                if (!empty($item['total_price']) && (float)$item['total_price'] > 0) {
                    $priceStr = number_format((float)$item['total_price'], 0) . ' ISK';
                    $priceW   = $this->tw($priceStr, self::FS_TINY);
                    $this->txt($img, $priceStr, $priceRightX - $priceW, $y + 14, self::FS_TINY, self::C_WHITE);
                    $nameMaxW = $priceRightX - $priceW - $nameX - 4;
                } else {
                    $nameMaxW = $priceRightX - $nameX - 4;
                }

                $this->txtTrunc($img, $itemName, $nameX, $y + 12, self::FS_SMALL, self::C_WHITE, $nameMaxW);

                $y += $rowH;
            }
            $y += 6; // 槽位间距
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  底部: 总价值
    // ─────────────────────────────────────────────────────────────────────

    protected function drawFooter($img, array $data, int $totalH): void
    {
        $p  = self::PADDING;
        $y  = $totalH - self::FOOTER_HEIGHT;

        $bg = $this->c($img, self::C_PANEL);
        imagefilledrectangle($img, 0, $y, self::CANVAS_WIDTH - 1, $totalH - 1, $bg);

        $border = $this->c($img, self::C_BORDER);
        imageline($img, 0, $y, self::CANVAS_WIDTH - 1, $y, $border);

        $totalVal   = $this->calcTotalValue($data['items_by_slot'] ?? []);
        $droppedVal = $this->calcDroppedValue($data['items_by_slot'] ?? []);

        // 总价值: 右对齐绿色
        $totalLabel = '总价值：';
        $totalText  = number_format($totalVal, 0) . ' ISK';
        $tlw  = $this->tw($totalLabel, self::FS_LARGE, true);
        $tvw  = $this->tw($totalText,  self::FS_LARGE, true);
        $trX  = self::CANVAS_WIDTH - $p - $tlw - $tvw;
        $this->txt($img, $totalLabel, $trX,        $y + 14, self::FS_LARGE, self::C_WHITE, true);
        $this->txt($img, $totalText,  $trX + $tlw, $y + 14, self::FS_LARGE, self::C_GREEN, true);

        // 总掉落: 右对齐淡蓝色
        if ($droppedVal > 0) {
            $dropLabel = '总掉落：';
            $dropText  = number_format($droppedVal, 0) . ' ISK';
            $dlw  = $this->tw($dropLabel, self::FS_NORMAL);
            $dvw  = $this->tw($dropText,  self::FS_NORMAL);
            $drX  = self::CANVAS_WIDTH - $p - $dlw - $dvw;
            $this->txt($img, $dropLabel, $drX,        $y + 38, self::FS_NORMAL, self::C_WHITE);
            $this->txt($img, $dropText,  $drX + $dlw, $y + 38, self::FS_NORMAL, self::C_LIGHT_BLUE);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  高度计算
    // ─────────────────────────────────────────────────────────────────────

    protected function calcAttackerH(array $attackers): int
    {
        $n = count($attackers);
        if ($n === 0) return 40;

        $finalIdx = -1;
        $maxIdx   = -1;
        $maxDmg   = -1;
        foreach ($attackers as $i => $atk) {
            if ($atk['final_blow'] ?? false) $finalIdx = $i;
            if ((int)($atk['damage_done'] ?? 0) > $maxDmg) {
                $maxDmg = (int)($atk['damage_done'] ?? 0);
                $maxIdx = $i;
            }
        }

        $featCount = ($finalIdx >= 0 ? 1 : 0) + ($maxIdx >= 0 && $maxIdx !== $finalIdx ? 1 : 0);
        $regCount  = $n - $featCount;

        return 8 + $featCount * (15 + self::ATK_ROW_H) + $regCount * self::ATK_ROW_H;
    }

    protected function calcItemH(array $itemsBySlot): int
    {
        $h = 8;
        foreach ($itemsBySlot as $items) {
            if (empty($items)) continue;
            $h += 25; // 槽位标题
            $h += count($items) * (self::ITEM_ICON + 6);
            $h += 6;
        }
        return $h;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  本地物品数据
    // ─────────────────────────────────────────────────────────────────────

    protected function loadLocalItems(): array
    {
        if ($this->localItems !== null) return $this->localItems;

        $path = base_path('data/eve_items.json');
        if (file_exists($path)) {
            $this->localItems = json_decode(file_get_contents($path), true) ?? [];
        } else {
            $this->localItems = [];
        }
        return $this->localItems;
    }

    /**
     * 解析物品名称: 优先本地 eve_items.json，找不到则用 API 返回值
     */
    protected function resolveItemName(int $typeId, string $fallback): string
    {
        if ($typeId <= 0) return $fallback ?: '未知';
        $items = $this->loadLocalItems();
        $name  = $items[(string)$typeId]['name'] ?? null;
        return $name ?: ($fallback ?: '未知');
    }

    /**
     * 获取物品类型组名 (用于舰船类型显示)
     * eve_items.json category 结构: ["舰船", "战列舰", "末日级"]
     * 返回第2个元素 (宽类别) 如 "战列舰"
     */
    protected function getItemGroup(int $typeId): ?string
    {
        if ($typeId <= 0) return null;
        $items    = $this->loadLocalItems();
        $item     = $items[(string)$typeId] ?? null;
        if (!$item) return null;
        $category = $item['category'] ?? [];
        if (count($category) >= 2) return $category[1];
        if (count($category) === 1) return $category[0];
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  EVE 图片获取 (带本地缓存)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 构建国服图片 URL
     * 国服格式: https://image.evepc.163.com/{Category}/{id}_{size}.{ext}
     */
    protected function buildImgUrl(string $cat, int $id, string $variant, int $size): string
    {
        $base = self::EVE_IMAGE_BASE;
        switch ("{$cat}/{$variant}") {
            case 'characters/portrait':
                return "{$base}/Character/{$id}_{$size}.jpg";
            case 'types/render':
                return "{$base}/Render/{$id}_{$size}.png";
            case 'types/icon':
                return "{$base}/Type/{$id}_{$size}.png";
            case 'corporations/logo':
                return "{$base}/Corporation/{$id}_{$size}.png";
            case 'alliances/logo':
                return "{$base}/Alliance/{$id}_{$size}.png";
            default:
                return "{$base}/{$cat}/{$id}/{$variant}?size={$size}";
        }
    }

    protected function fetchImg(string $cat, int $id, string $variant, int $size): ?\GdImage
    {
        if ($id <= 0) return null;

        $localPath = $this->cachePath . "/img_{$cat}_{$id}_{$variant}_{$size}.png";

        // 本地缓存 24h
        if (file_exists($localPath) && (time() - filemtime($localPath)) < 86400) {
            $img = @imagecreatefromstring(file_get_contents($localPath));
            if ($img) return $img;
        }

        try {
            $url  = $this->buildImgUrl($cat, $id, $variant, $size);
            $resp = Http::timeout(8)->get($url);
            if ($resp->successful()) {
                $body = $resp->body();
                file_put_contents($localPath, $body);
                $img = @imagecreatefromstring($body);
                if ($img) return $img;
            }
        } catch (\Throwable $e) {
            Log::warning("KM img fetch fail: {$cat}/{$id}/{$variant}", ['err' => $e->getMessage()]);
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  绘图辅助
    // ─────────────────────────────────────────────────────────────────────

    /** 绘制 TTF 文字 */
    protected function txt($img, string $str, int $x, int $y, int $size, array $rgb, bool $bold = false): void
    {
        $font = ($bold && file_exists($this->fontBoldPath)) ? $this->fontBoldPath : $this->fontPath;
        $col  = $this->c($img, $rgb);

        if (file_exists($font)) {
            imagettftext($img, $size, 0, $x, $y + $size, $col, $font, $str);
        } else {
            imagestring($img, 3, $x, $y, $str, $col);
        }
    }

    /** 绘制文字，超宽自动截断并加省略号 */
    protected function txtTrunc($img, string $str, int $x, int $y, int $size, array $rgb, int $maxW, bool $bold = false): void
    {
        if ($maxW <= 0 || !$str) {
            $this->txt($img, $str, $x, $y, $size, $rgb, $bold);
            return;
        }
        $font = ($bold && file_exists($this->fontBoldPath)) ? $this->fontBoldPath : $this->fontPath;
        if (!file_exists($font)) {
            $this->txt($img, $str, $x, $y, $size, $rgb, $bold);
            return;
        }

        $bbox = @imagettfbbox($size, 0, $font, $str);
        $w    = $bbox ? abs($bbox[2] - $bbox[0]) : 0;
        if ($w <= $maxW) {
            $this->txt($img, $str, $x, $y, $size, $rgb, $bold);
            return;
        }

        // 二分法找最大能显示的字符数
        $len = mb_strlen($str);
        $lo  = 1;
        $hi  = $len - 1;
        while ($lo < $hi) {
            $mid  = (int)(($lo + $hi + 1) / 2);
            $sub  = mb_substr($str, 0, $mid) . '…';
            $bbox = @imagettfbbox($size, 0, $font, $sub);
            $bw   = $bbox ? abs($bbox[2] - $bbox[0]) : 0;
            if ($bw <= $maxW) $lo = $mid;
            else              $hi = $mid - 1;
        }
        $str = mb_substr($str, 0, $lo) . '…';
        $this->txt($img, $str, $x, $y, $size, $rgb, $bold);
    }

    /** 获取文字渲染宽度 */
    protected function tw(string $str, int $size, bool $bold = false): int
    {
        $font = ($bold && file_exists($this->fontBoldPath)) ? $this->fontBoldPath : $this->fontPath;
        if (file_exists($font)) {
            $bbox = @imagettfbbox($size, 0, $font, $str);
            return $bbox ? abs($bbox[2] - $bbox[0]) : 0;
        }
        return (int)(strlen($str) * $size * 0.6);
    }

    /** 缩放绘制图片 */
    protected function scaled($img, $src, int $dx, int $dy, int $dw, int $dh): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    }

    /** 绘制占位符矩形 */
    protected function placeholder($img, int $x, int $y, int $w, int $h): void
    {
        $bg  = $this->c($img, [28, 32, 45]);
        $bdr = $this->c($img, self::C_BORDER);
        imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $bg);
        imagerectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $bdr);
    }

    /** 分配颜色 */
    protected function c($img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    /** 安等颜色 */
    protected function secColor(float $sec): array
    {
        if ($sec >= 0.9) return [  0, 230,   0];
        if ($sec >= 0.7) return [  0, 185,   0];
        if ($sec >= 0.5) return [255, 255,   0];
        if ($sec >= 0.3) return [255, 155,   0];
        if ($sec >= 0.1) return [255,  80,   0];
        if ($sec >  0.0) return [255,  40,   0];
        return [255, 0, 0];
    }

    /** ISK 格式化 */
    protected function formatIsk(float $v): string
    {
        if ($v >= 1e12) return number_format($v / 1e12, 2) . 'T';
        if ($v >= 1e9)  return number_format($v / 1e9,  2) . 'B';
        if ($v >= 1e6)  return number_format($v / 1e6,  2) . 'M';
        if ($v >= 1e3)  return number_format($v / 1e3,  1) . 'K';
        return number_format($v, 0);
    }

    /** 计算物品总价值 */
    protected function calcTotalValue(array $itemsBySlot): float
    {
        $t = 0.0;
        foreach ($itemsBySlot as $items) {
            foreach ($items as $item) {
                $t += (float)($item['total_price'] ?? 0);
            }
        }
        return $t;
    }

    /**
     * 获取舰船在吉他区域最低卖单价格 (国服 Serenity)
     * 有缓存；找不到订单返回 null
     */
    protected function getHullLowestSellPrice(int $typeId): ?float
    {
        if ($typeId <= 0) return null;
        $cacheKey  = "km_hull_price_{$typeId}";
        $cachePath = $this->cachePath . "/{$cacheKey}.json";

        // 24h 本地缓存
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 86400) {
            $cached = json_decode(file_get_contents($cachePath), true);
            return isset($cached['price']) ? (float)$cached['price'] : null;
        }

        try {
            $url  = 'https://esi.evetech.net/latest/markets/10000002/orders/';
            $resp = Http::timeout(6)->get($url, [
                'datasource' => 'serenity',
                'order_type' => 'sell',
                'type_id'    => $typeId,
            ]);
            if ($resp->successful()) {
                $orders = $resp->json();
                $sellPrices = array_filter(array_column($orders, 'price'), fn($p) => $p > 0);
                if (!empty($sellPrices)) {
                    $minPrice = min($sellPrices);
                    file_put_contents($cachePath, json_encode(['price' => $minPrice]));
                    return (float)$minPrice;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("KM hull price fetch fail: {$typeId}", ['err' => $e->getMessage()]);
        }

        file_put_contents($cachePath, json_encode(['price' => null]));
        return null;
    }

    /** 计算掉落物品总价值 */
    protected function calcDroppedValue(array $itemsBySlot): float
    {
        $t = 0.0;
        foreach ($itemsBySlot as $items) {
            foreach ($items as $item) {
                if (($item['status'] ?? '') === 'dropped') {
                    $t += (float)($item['total_price'] ?? 0);
                }
            }
        }
        return $t;
    }

    /** 清除指定 KM 的图片缓存 */
    public function clearCache(int $killId): void
    {
        $path = $this->cachePath . "/km_{$killId}.png";
        if (file_exists($path)) unlink($path);
    }
}
