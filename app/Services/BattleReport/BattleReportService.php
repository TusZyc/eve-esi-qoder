<?php

namespace App\Services\BattleReport;

use App\Services\Killmail\BetaKbApiClient;
use App\Services\Killmail\KillmailEnrichService;
use App\Services\KillmailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 战场报告服务
 * 
 * 负责搜索KM、提取阵营、生成战场报告
 */
class BattleReportService
{
    protected BetaKbApiClient $kbClient;
    protected KillmailEnrichService $enrichService;
    protected KillmailService $killmailService;
    protected ?array $systemJumps = null;

    public function __construct(
        BetaKbApiClient $kbClient,
        KillmailEnrichService $enrichService,
        KillmailService $killmailService
    ) {
        $this->kbClient = $kbClient;
        $this->enrichService = $enrichService;
        $this->killmailService = $killmailService;
    }

    /**
     * 搜索星系KM并返回阵营预览
     * 
     * @param int $systemId 星系ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param bool $includeNearby 是否包含邻近星系
     * @return array
     */
    public function searchKills(int $systemId, string $startTime, string $endTime, bool $includeNearby): array
    {
        // 获取要搜索的星系列表
        $systemIds = $this->getSystemIdsWithNearby($systemId, $includeNearby);
        
        Log::debug("BattleReport: 搜索星系 " . implode(', ', $systemIds) . ", 时间 {$startTime} ~ {$endTime}");

        // 搜索KM
        $allKills = [];
        foreach ($systemIds as $sysId) {
            $kills = $this->searchSystemKills($sysId, $startTime, $endTime);
            $allKills = array_merge($allKills, $kills);
        }

        // 去重（按kill_id）
        $seen = [];
        $uniqueKills = [];
        foreach ($allKills as $kill) {
            if (!isset($seen[$kill['kill_id']])) {
                $seen[$kill['kill_id']] = true;
                $uniqueKills[] = $kill;
            }
        }

        Log::debug("BattleReport: 找到 " . count($uniqueKills) . " 条KM");

        if (empty($uniqueKills)) {
            return [
                'total_kills' => 0,
                'factions' => [],
                'system_names' => $this->getSystemNames($systemIds),
            ];
        }

        // 富化KM数据（使用 KillmailService 以获取完整物品数据）
        [$enrichedKills, $detailsMap] = $this->killmailService->enrichKillList($uniqueKills);

        // 提取阵营
        $factions = $this->extractFactions($enrichedKills, $detailsMap);

        // 按损失ISK排序
        usort($factions, function ($a, $b) {
            return $b['loss_isk'] <=> $a['loss_isk'];
        });

        return [
            'total_kills' => count($enrichedKills),
            'factions' => $factions,
            'system_names' => $this->getSystemNames($systemIds),
        ];
    }

    /**
     * 生成完整战场报告
     * 
     * @param int $systemId 星系ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param bool $includeNearby 是否包含邻近星系
     * @param array $redFactionIds 红方阵营ID列表
     * @param array $blueFactionIds 蓝方阵营ID列表
     * @return array
     */
    public function generateReport(
        int $systemId,
        string $startTime,
        string $endTime,
        bool $includeNearby,
        array $redFactionIds,
        array $blueFactionIds
    ): array {
        // 获取要搜索的星系列表
        $systemIds = $this->getSystemIdsWithNearby($systemId, $includeNearby);

        // 搜索KM
        $allKills = [];
        foreach ($systemIds as $sysId) {
            $kills = $this->searchSystemKills($sysId, $startTime, $endTime);
            $allKills = array_merge($allKills, $kills);
        }

        // 去重
        $seen = [];
        $uniqueKills = [];
        foreach ($allKills as $kill) {
            if (!isset($seen[$kill['kill_id']])) {
                $seen[$kill['kill_id']] = true;
                $uniqueKills[] = $kill;
            }
        }

        if (empty($uniqueKills)) {
            return [
                'success' => false,
                'message' => '未找到任何KM数据',
            ];
        }

        // 富化KM数据（使用 KillmailService 以获取完整物品数据）
        [$enrichedKills, $detailsMap] = $this->killmailService->enrichKillList($uniqueKills);

        // 计算统计数据
        $stats = $this->calculateStats($enrichedKills, $redFactionIds, $blueFactionIds);

        // 舰船统计
        $shipStats = $this->calculateShipStats($enrichedKills, $redFactionIds, $blueFactionIds);

        // 参与阵营详情
        $redFactionDetails = $this->getFactionDetails($enrichedKills, $redFactionIds, 'red');
        $blueFactionDetails = $this->getFactionDetails($enrichedKills, $blueFactionIds, 'blue');

        // KM列表（按损失方分类）
        $kmList = $this->buildKmList($enrichedKills, $redFactionIds, $blueFactionIds);

        return [
            'success' => true,
            'stats' => $stats,
            'ship_stats' => $shipStats,
            'red_factions' => $redFactionDetails,
            'blue_factions' => $blueFactionDetails,
            'km_list' => $kmList,
            'system_names' => $this->getSystemNames($systemIds),
            'time_range' => [
                'start' => $startTime,
                'end' => $endTime,
            ],
        ];
    }

    /**
     * 搜索单个星系的KM
     */
    protected function searchSystemKills(int $systemId, string $startTime, string $endTime): array
    {
        $cacheKey = "battlereport:{$systemId}:" . md5("{$startTime}|{$endTime}");
        
        return Cache::remember($cacheKey, 300, function () use ($systemId, $startTime, $endTime) {
            $params = [
                'systems' => [$systemId],
                'start_date' => $startTime,
                'end_date' => $endTime,
            ];

            return $this->kbClient->fetchBetaSearchKillsAdvanced($params);
        });
    }

    /**
     * 获取星系及其邻近星系ID列表
     */
    protected function getSystemIdsWithNearby(int $systemId, bool $includeNearby): array
    {
        $systemIds = [$systemId];

        if ($includeNearby) {
            $jumps = $this->loadSystemJumps();
            $nearby = $jumps[$systemId] ?? [];
            $systemIds = array_merge($systemIds, $nearby);
        }

        return array_unique($systemIds);
    }

    /**
     * 加载星系跳跃数据
     */
    protected function loadSystemJumps(): array
    {
        if ($this->systemJumps !== null) {
            return $this->systemJumps;
        }

        $path = base_path('data/solar_system_jumps.json');
        if (file_exists($path)) {
            $this->systemJumps = json_decode(file_get_contents($path), true) ?? [];
        } else {
            $this->systemJumps = [];
        }

        return $this->systemJumps;
    }

    /**
     * 从KM列表提取所有阵营
     */
    protected function extractFactions(array $enrichedKills, array $detailsMap): array
    {
        $factions = [];

        foreach ($enrichedKills as $kill) {
            $killId = $kill['kill_id'];
            $detail = $detailsMap[$killId] ?? null;
            $totalValue = $kill['total_value'] ?? 0;

            // 受害者阵营
            $victimDetail = $detail['victim'] ?? [];
            $this->addFactionFromEntity(
                $factions,
                $victimDetail['alliance_id'] ?? null,
                $victimDetail['alliance_name'] ?? null,
                $victimDetail['corporation_id'] ?? null,
                $victimDetail['corporation_name'] ?? null,
                $totalValue,
                true,
                $victimDetail['character_id'] ?? null
            );

            // 攻击者阵营
            foreach ($detail['attackers'] ?? [] as $atk) {
                $isFinalBlow = $atk['final_blow'] ?? false;
                $this->addFactionFromEntity(
                    $factions,
                    $atk['alliance_id'] ?? null,
                    $atk['alliance_name'] ?? null,
                    $atk['corporation_id'] ?? null,
                    $atk['corporation_name'] ?? null,
                    0,
                    false,
                    $atk['character_id'] ?? null,
                    $isFinalBlow
                );
            }
        }

        return array_values($factions);
    }

    /**
     * 从实体信息添加阵营
     */
    protected function addFactionFromEntity(
        array &$factions,
        ?int $allianceId,
        ?string $allianceName,
        ?int $corporationId,
        ?string $corporationName,
        float $lossValue,
        bool $isVictim,
        ?int $characterId,
        bool $isFinalBlow = false
    ): void {
        // 有联盟用联盟，无联盟用军团
        $factionId = $allianceId ?? $corporationId;
        $factionName = $allianceName ?? $corporationName ?? '未知阵营';

        if (!$factionId) return;

        if (!isset($factions[$factionId])) {
            $factions[$factionId] = [
                'id' => $factionId,
                'name' => $factionName,
                'is_alliance' => $allianceId !== null,
                'participants' => [],
                'loss_isk' => 0,
                'loss_ships' => 0,
                'kills' => 0,
            ];
        }

        // 统计
        if ($isVictim) {
            $factions[$factionId]['loss_isk'] += $lossValue;
            $factions[$factionId]['loss_ships']++;
        } else {
            if ($isFinalBlow) {
                $factions[$factionId]['kills']++;
            }
        }

        // 参与者
        if ($characterId) {
            $factions[$factionId]['participants'][$characterId] = true;
        }
    }

    /**
     * 计算统计数据
     */
    protected function calculateStats(array $enrichedKills, array $redFactionIds, array $blueFactionIds): array
    {
        $stats = [
            'red' => [
                'loss_isk' => 0,
                'loss_ships' => 0,
                'kills' => 0,  // 红方击杀数 = 蓝方损失舰船数
                'participants' => [],
            ],
            'blue' => [
                'loss_isk' => 0,
                'loss_ships' => 0,
                'kills' => 0,  // 蓝方击杀数 = 红方损失舰船数
                'participants' => [],
            ],
        ];

        foreach ($enrichedKills as $kill) {
            // 优先使用联盟ID，无联盟时使用军团ID
            $victimFactionId = $kill['victim_alliance_id'] ?? $kill['victim_corporation_id'] ?? null;
            $totalValue = $kill['total_value'] ?? 0;

            // 统计损失（同时计算对方的击杀数）
            if (in_array($victimFactionId, $redFactionIds)) {
                $stats['red']['loss_isk'] += $totalValue;
                $stats['red']['loss_ships']++;
                $stats['blue']['kills']++;  // 蓝方击杀数+1
                if ($kill['victim_id'] ?? null) {
                    $stats['red']['participants'][$kill['victim_id']] = true;
                }
            } elseif (in_array($victimFactionId, $blueFactionIds)) {
                $stats['blue']['loss_isk'] += $totalValue;
                $stats['blue']['loss_ships']++;
                $stats['red']['kills']++;  // 红方击杀数+1
                if ($kill['victim_id'] ?? null) {
                    $stats['blue']['participants'][$kill['victim_id']] = true;
                }
            }
        }

        $stats['red']['participant_count'] = count($stats['red']['participants']);
        $stats['blue']['participant_count'] = count($stats['blue']['participants']);

        return $stats;
    }

    /**
     * 计算舰船统计
     */
    protected function calculateShipStats(array $enrichedKills, array $redFactionIds, array $blueFactionIds): array
    {
        $shipStats = [];

        foreach ($enrichedKills as $kill) {
            $shipTypeId = $kill['ship_type_id'] ?? null;
            $shipName = $kill['ship_name'] ?? '未知舰船';
            $victimFactionId = $kill['victim_alliance_id'] ?? $kill['victim_corporation_id'] ?? null;
            $totalValue = $kill['total_value'] ?? 0;

            if (!$shipTypeId) continue;

            if (!isset($shipStats[$shipTypeId])) {
                $shipStats[$shipTypeId] = [
                    'ship_type_id' => $shipTypeId,
                    'ship_name' => $shipName,
                    'red_loss' => 0,
                    'blue_loss' => 0,
                    'red_value' => 0,
                    'blue_value' => 0,
                ];
            }

            if (in_array($victimFactionId, $redFactionIds)) {
                $shipStats[$shipTypeId]['red_loss']++;
                $shipStats[$shipTypeId]['red_value'] += $totalValue;
            } elseif (in_array($victimFactionId, $blueFactionIds)) {
                $shipStats[$shipTypeId]['blue_loss']++;
                $shipStats[$shipTypeId]['blue_value'] += $totalValue;
            }
        }

        // 按损失数量排序
        usort($shipStats, function ($a, $b) {
            return ($b['red_loss'] + $b['blue_loss']) <=> ($a['red_loss'] + $a['blue_loss']);
        });

        return $shipStats;
    }

    /**
     * 获取阵营详情
     */
    protected function getFactionDetails(array $enrichedKills, array $factionIds, string $side): array
    {
        $details = [];

        foreach ($enrichedKills as $kill) {
            $victimFactionId = $kill['victim_alliance_id'] ?? $kill['victim_corporation_id'] ?? null;

            if (!in_array($victimFactionId, $factionIds)) continue;

            if (!isset($details[$victimFactionId])) {
                $details[$victimFactionId] = [
                    'id' => $victimFactionId,
                    'name' => $kill['victim_alliance'] ?? $kill['victim_corp'] ?? '未知阵营',
                    'is_alliance' => $kill['victim_alliance_id'] ?? null !== null,
                    'participants' => [],
                    'loss_isk' => 0,
                    'loss_ships' => 0,
                ];
            }

            $details[$victimFactionId]['loss_isk'] += $kill['total_value'] ?? 0;
            $details[$victimFactionId]['loss_ships']++;

            if ($kill['victim_id'] ?? null) {
                $details[$victimFactionId]['participants'][$kill['victim_id']] = true;
            }
        }

        // 计算参与人数并排序
        $result = [];
        foreach ($details as $id => $detail) {
            $detail['participant_count'] = count($detail['participants']);
            unset($detail['participants']);
            $result[] = $detail;
        }

        usort($result, function ($a, $b) {
            return $b['loss_isk'] <=> $a['loss_isk'];
        });

        return $result;
    }

    /**
     * 构建KM列表
     */
    protected function buildKmList(array $enrichedKills, array $redFactionIds, array $blueFactionIds): array
    {
        $list = [];

        foreach ($enrichedKills as $kill) {
            $victimFactionId = $kill['victim_alliance_id'] ?? $kill['victim_corporation_id'] ?? null;

            $side = null;
            if (in_array($victimFactionId, $redFactionIds)) {
                $side = 'red';
            } elseif (in_array($victimFactionId, $blueFactionIds)) {
                $side = 'blue';
            }

            if ($side === null) continue;

            $list[] = [
                'kill_id' => $kill['kill_id'],
                'kill_time' => $kill['kill_time'] ?? null,
                'victim_name' => $kill['victim_name'] ?? null,
                'victim_id' => $kill['victim_id'] ?? null,
                'ship_name' => $kill['ship_name'] ?? null,
                'ship_type_id' => $kill['ship_type_id'] ?? null,
                'system_name' => $kill['system_name'] ?? null,
                'total_value' => $kill['total_value'] ?? 0,
                'side' => $side,
                'victim_alliance' => $kill['victim_alliance'] ?? null,
                'victim_corp' => $kill['victim_corp'] ?? null,
                'final_blow_name' => $kill['final_blow_name'] ?? null,
                'final_blow_alliance' => $kill['final_blow_alliance'] ?? null,
            ];
        }

        // 按时间倒序
        usort($list, function ($a, $b) {
            return strcmp($b['kill_time'] ?? '', $a['kill_time'] ?? '');
        });

        return $list;
    }

    /**
     * 获取星系名称列表
     */
    protected function getSystemNames(array $systemIds): array
    {
        $names = [];
        foreach ($systemIds as $id) {
            $cached = Cache::get("eve_name_{$id}");
            if ($cached) {
                $names[$id] = $cached;
            } else {
                $names[$id] = "星系#{$id}";
            }
        }
        return $names;
    }
}