<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * KM 数据过滤服务
 * 
 * 负责KM列表的条件过滤和参与类型筛选
 * 从 KillmailService 中提取，职责更清晰
 */
class KillmailFilterService
{
    /**
     * 按条件过滤 KM 列表
     * 
     * @param array $kills KM列表
     * @param array $params 过滤参数
     *   - ship_id: 舰船类型ID
     *   - system_id: 星系ID
     *   - time_start: 开始时间
     *   - time_end: 结束时间
     * @return array 过滤后的KM列表
     */
    public function filterKills(array $kills, array $params): array
    {
        $shipId = $params['ship_id'] ?? null;
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        return array_values(array_filter($kills, function ($kill) use ($shipId, $systemId, $timeStart, $timeEnd) {
            // 舰船过滤
            if ($shipId && !empty($kill['ship_type_id']) && (int) $kill['ship_type_id'] !== (int) $shipId) {
                return false;
            }
            
            // 星系过滤
            if ($systemId && !empty($kill['system_id']) && (int) $kill['system_id'] !== (int) $systemId) {
                return false;
            }
            
            // 开始时间过滤
            if ($timeStart && !empty($kill['kill_timestamp'])) {
                $startTs = strtotime($timeStart);
                if ($startTs && $kill['kill_timestamp'] < $startTs) {
                    return false;
                }
            }
            
            // 结束时间过滤
            if ($timeEnd && !empty($kill['kill_timestamp'])) {
                // 如果时间字符串包含时间部分，直接使用；否则设为当天 23:59:59
                if (strpos($timeEnd, 'T') !== false || strpos($timeEnd, ':') !== false) {
                    $endTs = strtotime($timeEnd);
                } else {
                    $endTs = strtotime($timeEnd . ' 23:59:59');
                }
                if ($endTs && $kill['kill_timestamp'] > $endTs) {
                    return false;
                }
            }
            
            return true;
        }));
    }

    /**
     * 基于 protobuf 列表数据的预过滤
     * 
     * 在获取富化详情前，根据已知数据进行初步过滤，减少后续API调用
     * 
     * @param array $kills KM列表
     * @param string $entityType 实体类型 (pilot/corporation/alliance)
     * @param int $entityId 实体ID
     * @param string $involvement 参与类型 (victim/finalblow/attacker)
     * @return array 预过滤后的KM列表
     */
    public function preFilterByInvolvement(array $kills, string $entityType, int $entityId, string $involvement): array
    {
        // 仅对 pilot + victim 组合进行预过滤
        // 因为 protobuf 列表数据中包含 victim_id 字段
        if ($involvement === 'victim' && $entityType === 'pilot') {
            $filtered = array_values(array_filter($kills, function ($kill) use ($entityId) {
                return !empty($kill['victim_id']) && (int) $kill['victim_id'] === $entityId;
            }));
            Log::debug("preFilterByInvolvement: pilot+victim 预过滤, 输入 " . count($kills) . " 条, 输出 " . count($filtered) . " 条");
            return $filtered;
        }
        
        return $kills;
    }

    /**
     * 根据参与类型过滤富化后的 KM 列表
     * 
     * @param array $enrichedKills 富化后的KM列表
     * @param string $entityType 实体类型 (pilot/corporation/alliance)
     * @param int $entityId 实体ID
     * @param string $involvement 参与类型 (victim/finalblow/attacker)
     * @param array $detailsMap KM详情映射 (可选，避免重复获取)
     * @param callable|null $getKillDetails 获取KM详情的回调函数
     * @return array 过滤后的KM列表
     */
    public function filterByInvolvement(
        array $enrichedKills, 
        string $entityType, 
        int $entityId, 
        string $involvement, 
        array $detailsMap = [],
        ?callable $getKillDetails = null
    ): array {
        $matchCount = 0;
        $noDetailCount = 0;

        $result = array_values(array_filter($enrichedKills, function ($kill) use (
            $entityType, 
            $entityId, 
            $involvement, 
            $detailsMap, 
            $getKillDetails,
            &$matchCount, 
            &$noDetailCount
        ) {
            $killId = $kill['kill_id'];
            
            // 优先从详情映射获取
            $detail = $detailsMap[$killId] ?? Cache::get("kb:kill:{$killId}");
            
            // 如果没有详情且提供了回调函数，尝试获取
            if (!$detail && $getKillDetails) {
                try {
                    $detail = $getKillDetails($killId);
                } catch (\Exception $e) {
                    $noDetailCount++;
                    return false;
                }
            }

            if (!$detail || empty($detail['victim'])) {
                $noDetailCount++;
                return false;
            }

            $matched = false;
            switch ($involvement) {
                case 'victim':
                    $matched = $this->entityMatchesParticipant($detail['victim'], $entityType, $entityId);
                    break;
                    
                case 'finalblow':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (!empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                    
                case 'attacker':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                    
                default:
                    $matched = true;
            }

            if ($matched) $matchCount++;
            return $matched;
        }));

        Log::debug("filterByInvolvement: involvement={$involvement}, entity={$entityType}:{$entityId}, 输入 " . count($enrichedKills) . " 条, 匹配 {$matchCount} 条, 无详情 {$noDetailCount} 条");

        return $result;
    }

    /**
     * 判断某个参与者是否匹配搜索的实体
     * 
     * @param array $participant 参与者数据
     * @param string $entityType 实体类型 (pilot/corporation/alliance)
     * @param int $entityId 实体ID
     * @return bool 是否匹配
     */
    public function entityMatchesParticipant(array $participant, string $entityType, int $entityId): bool
    {
        switch ($entityType) {
            case 'pilot':
                return !empty($participant['character_id']) && (int) $participant['character_id'] === $entityId;
            case 'corporation':
                return !empty($participant['corporation_id']) && (int) $participant['corporation_id'] === $entityId;
            case 'alliance':
                return !empty($participant['alliance_id']) && (int) $participant['alliance_id'] === $entityId;
            default:
                return false;
        }
    }

    /**
     * 批量检查 KM 是否符合参与类型条件
     * 
     * @param array $kills KM列表
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @param string $involvement 参与类型
     * @param array $detailsMap KM详情映射
     * @return array 符合条件的KM ID列表
     */
    public function getMatchingKillIds(
        array $kills, 
        string $entityType, 
        int $entityId, 
        string $involvement,
        array $detailsMap = []
    ): array {
        $matchingIds = [];
        
        foreach ($kills as $kill) {
            $killId = $kill['kill_id'];
            $detail = $detailsMap[$killId] ?? Cache::get("kb:kill:{$killId}");
            
            if (!$detail || empty($detail['victim'])) {
                continue;
            }
            
            $matched = false;
            switch ($involvement) {
                case 'victim':
                    $matched = $this->entityMatchesParticipant($detail['victim'], $entityType, $entityId);
                    break;
                case 'finalblow':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (!empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                case 'attacker':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                default:
                    $matched = true;
            }
            
            if ($matched) {
                $matchingIds[] = $killId;
            }
        }
        
        return $matchingIds;
    }
}