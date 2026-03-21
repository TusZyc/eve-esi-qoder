<?php

namespace App\Services\Fleet;

use App\Models\Fleet\FleetOperation;
use App\Models\Fleet\FleetOperationConfig;
use App\Models\Fleet\FleetAttendanceSummary;
use App\Models\Fleet\FleetMemberSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 参与度评分计算服务
 */
class AttendanceCalculator
{
    /**
     * 计算行动中所有成员的出勤评分
     *
     * @param FleetOperation $operation 行动记录
     * @return array 出勤汇总列表
     */
    public function calculateAll(FleetOperation $operation): array
    {
        $config = $operation->config ?? FleetOperationConfig::getDefault();
        $summaries = [];

        // 获取所有成员的快照数据，按角色分组
        $memberSnapshots = FleetMemberSnapshot::whereIn('snapshot_id', 
            $operation->snapshots()->pluck('id')
        )
            ->select(
                'character_id',
                'character_name',
                'corporation_id',
                'corporation_name',
                DB::raw('COUNT(*) as total_snapshots'),
                DB::raw('MIN(created_at) as first_seen_at'),
                DB::raw('MAX(created_at) as last_seen_at'),
                DB::raw('SUM(CASE WHEN in_station = 1 THEN 1 ELSE 0 END) as in_station_count'),
                DB::raw('AVG(jumps_from_commander) as avg_jumps'),
                DB::raw('SUM(CASE WHEN jumps_from_commander <= ? THEN 1 ELSE 0 END) as jumps_qualified_count')
            )
            ->addBinding($config->distance_max_jumps, 'select')
            ->groupBy('character_id', 'character_name', 'corporation_id', 'corporation_name')
            ->get();

        // 获取指挥官在各星系的快照次数（用于计算星系重叠）
        $commanderSystemSnapshots = FleetMemberSnapshot::whereIn('snapshot_id', $operation->snapshots()->pluck('id'))
            ->where('character_id', $operation->commander_character_id)
            ->pluck('solar_system_id')
            ->countBy();

        // 获取行动时长
        $operationDuration = $operation->getDurationMinutes();
        $operationStart = $operation->started_at;

        foreach ($memberSnapshots as $memberData) {
            // 获取该成员的详细快照
            $memberDetails = FleetMemberSnapshot::whereIn('snapshot_id', $operation->snapshots()->pluck('id'))
                ->where('character_id', $memberData->character_id)
                ->get();

            // 计算出勤时长（基于首次和最后出现时间）
            $attendanceDuration = 0;
            if ($memberData->first_seen_at && $memberData->last_seen_at) {
                $firstSeen = $memberData->first_seen_at;
                $lastSeen = $memberData->last_seen_at;
                
                // 简化计算：假设成员在两次快照之间都在线
                // 更精确的方法需要考虑快照间隔
                $totalSnapshots = $memberDetails->count();
                $avgInterval = $operation->snapshot_interval ?? 60; // 秒
                $attendanceDuration = (int) ($totalSnapshots * $avgInterval / 60); // 分钟
            }

            // 计算加入延迟：用首次出现在快照的时间，而不是 join_time（加入舰队时间）
            // join_time 是成员加入舰队的时间，可能在行动开始前就已经在舰队中了
            $joinDelay = 0;
            if ($memberData->first_seen_at) {
                $firstSeen = \Carbon\Carbon::parse($memberData->first_seen_at);
                $joinDelay = max(0, (int) $operationStart->diffInMinutes($firstSeen));
            }

            // 统计舰船使用
            $shipsUsed = $memberDetails->whereNotNull('ship_type_id')
                ->groupBy('ship_type_id')
                ->map(function ($group, $shipId) {
                    return [
                        'type_id' => $shipId,
                        'name' => $group->first()->ship_type_name,
                        'count' => $group->count(),
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->toArray();

            // 主要舰船
            $primaryShip = $shipsUsed[0] ?? null;

            // 统计到访星系
            $systemsVisited = $memberDetails->groupBy('solar_system_id')
                ->map(function ($group, $systemId) {
                    return [
                        'system_id' => $systemId,
                        'name' => $group->first()->solar_system_name,
                        'count' => $group->count(),
                    ];
                })
                ->values()
                ->toArray();

            // 计算星系重叠率（与指挥官在同一星系的比例）
            $systemOverlapPercent = $this->calculateSystemOverlap($memberDetails, $commanderSystemSnapshots);

            // 计算跳跃距离合格率
            $jumpsQualifiedPercent = $memberData->total_snapshots > 0
                ? round(($memberData->jumps_qualified_count / $memberData->total_snapshots) * 100, 2)
                : 0;

            // 在站比例
            $inStationPercent = $memberData->total_snapshots > 0
                ? round(($memberData->in_station_count / $memberData->total_snapshots) * 100, 2)
                : 0;

            // 创建或更新汇总记录
            $summary = FleetAttendanceSummary::updateOrCreate(
                [
                    'operation_id' => $operation->id,
                    'character_id' => $memberData->character_id,
                ],
                [
                    'character_name' => $memberData->character_name,
                    'corporation_id' => $memberData->corporation_id,
                    'corporation_name' => $memberData->corporation_name,
                    'first_seen_at' => $memberData->first_seen_at,
                    'last_seen_at' => $memberData->last_seen_at,
                    'total_snapshots' => $memberData->total_snapshots,
                    'attendance_duration_minutes' => $attendanceDuration,
                    'join_delay_minutes' => $joinDelay,
                    'ships_used' => $shipsUsed,
                    'primary_ship_id' => $primaryShip['type_id'] ?? null,
                    'primary_ship_name' => $primaryShip['name'] ?? null,
                    'systems_visited' => $systemsVisited,
                    'system_overlap_percent' => $systemOverlapPercent,
                    'avg_jumps_from_commander' => round($memberData->avg_jumps ?? 0, 2),
                    'jumps_qualified_percent' => $jumpsQualifiedPercent,
                    'in_station_snapshots' => $memberData->in_station_count,
                    'in_station_percent' => $inStationPercent,
                ]
            );

            // 计算各维度得分
            $scores = $this->calculateScores($summary, $config, $operationDuration);
            
            // 更新得分
            $summary->update([
                'score_duration' => $scores['duration']['weighted'],
                'score_join_time' => $scores['join_time']['weighted'],
                'score_ship_type' => $scores['ship_type']['weighted'],
                'score_systems' => $scores['systems']['weighted'],
                'score_distance' => $scores['distance']['weighted'],
                'score_in_station' => $scores['in_station']['weighted'],
                'total_score' => $scores['total'],
                'grade' => $scores['grade'],
                'is_full_participant' => $scores['total'] >= 70,
            ]);

            $summaries[] = $summary;
        }

        Log::info('[AttendanceCalculator] 评分计算完成', [
            'operation_id' => $operation->id,
            'member_count' => count($summaries),
        ]);

        return $summaries;
    }

    /**
     * 计算星系重叠率
     */
    protected function calculateSystemOverlap($memberDetails, $commanderSystemSnapshots): float
    {
        $totalSnapshots = $memberDetails->count();
        if ($totalSnapshots === 0) {
            return 0;
        }

        $overlapCount = 0;
        foreach ($memberDetails as $snapshot) {
            if (isset($commanderSystemSnapshots[$snapshot->solar_system_id])) {
                $overlapCount++;
            }
        }

        return round(($overlapCount / $totalSnapshots) * 100, 2);
    }

    /**
     * 计算各维度得分
     */
    protected function calculateScores(FleetAttendanceSummary $summary, FleetOperationConfig $config, int $operationDuration): array
    {
        $scores = [
            'duration' => $this->calculateDurationScore($summary, $config, $operationDuration),
            'join_time' => $this->calculateJoinTimeScore($summary, $config),
            'ship_type' => $this->calculateShipTypeScore($summary, $config),
            'systems' => $this->calculateSystemsScore($summary, $config),
            'distance' => $this->calculateDistanceScore($summary, $config),
            'in_station' => $this->calculateInStationScore($summary, $config),
        ];

        // 计算总分
        $total = 0;
        foreach ($scores as $score) {
            $total += $score['weighted'];
        }

        $scores['total'] = round($total, 2);
        $scores['grade'] = $this->getGrade($scores['total']);

        return $scores;
    }

    /**
     * 1. 在队时长评分
     */
    protected function calculateDurationScore(FleetAttendanceSummary $summary, FleetOperationConfig $config, int $operationDuration): array
    {
        if (!$config->duration_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        $onlinePercent = $operationDuration > 0
            ? ($summary->attendance_duration_minutes / $operationDuration) * 100
            : 0;

        // 低于最低要求：按比例扣分
        if ($onlinePercent < $config->duration_min_percent) {
            $rawScore = min(100, $onlinePercent);
        } else {
            $rawScore = 100;
        }

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->duration_weight, 2),
        ];
    }

    /**
     * 2. 加入时间评分
     */
    protected function calculateJoinTimeScore(FleetAttendanceSummary $summary, FleetOperationConfig $config): array
    {
        if (!$config->join_time_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        $delayMinutes = $summary->join_delay_minutes;
        $graceMinutes = $config->join_time_grace_minutes;

        if ($delayMinutes <= 0) {
            $rawScore = 100;
        } elseif ($delayMinutes <= $graceMinutes) {
            // 在宽限时间内，最多扣30分
            $rawScore = 100 - ($delayMinutes / $graceMinutes * 30);
        } else {
            // 严重迟到
            $rawScore = max(0, 70 - ($delayMinutes - $graceMinutes));
        }

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->join_time_weight, 2),
        ];
    }

    /**
     * 3. 舰船类型评分
     */
    protected function calculateShipTypeScore(FleetAttendanceSummary $summary, FleetOperationConfig $config): array
    {
        if (!$config->ship_type_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        $requiredIds = $config->ship_type_required_ids ?? [];
        $primaryShipId = $summary->primary_ship_id;

        // 如果没有设置期望船型，默认给满分
        if (empty($requiredIds)) {
            $rawScore = 100;
        } elseif (in_array($primaryShipId, $requiredIds)) {
            $rawScore = 100;
        } else {
            $rawScore = 100 - $config->ship_type_penalty_percent;
        }

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->ship_type_weight, 2),
        ];
    }

    /**
     * 4. 到访星系评分
     */
    protected function calculateSystemsScore(FleetAttendanceSummary $summary, FleetOperationConfig $config): array
    {
        if (!$config->systems_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        $overlapPercent = $summary->system_overlap_percent;
        $minPercent = $config->systems_overlap_min_percent;

        if ($overlapPercent >= $minPercent) {
            $rawScore = 100;
        } else {
            $rawScore = ($overlapPercent / $minPercent) * 100;
        }

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->systems_weight, 2),
        ];
    }

    /**
     * 5. 离队距离评分
     */
    protected function calculateDistanceScore(FleetAttendanceSummary $summary, FleetOperationConfig $config): array
    {
        if (!$config->distance_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        // 距离合格率直接作为得分
        $rawScore = $summary->jumps_qualified_percent;

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->distance_weight, 2),
        ];
    }

    /**
     * 6. 在站次数评分
     */
    protected function calculateInStationScore(FleetAttendanceSummary $summary, FleetOperationConfig $config): array
    {
        if (!$config->in_station_enabled) {
            return ['raw' => 0, 'weighted' => 0];
        }

        $inStationPercent = $summary->in_station_percent;
        $maxPercent = $config->in_station_max_percent;

        if ($inStationPercent <= $maxPercent) {
            $rawScore = 100;
        } else {
            // 超过容忍上限，按比例扣分
            $excess = $inStationPercent - $maxPercent;
            $rawScore = max(0, 100 - $excess * 2);
        }

        return [
            'raw' => round($rawScore, 2),
            'weighted' => round(($rawScore / 100) * $config->in_station_weight, 2),
        ];
    }

    /**
     * 根据总分获取等级
     */
    protected function getGrade(float $score): string
    {
        if ($score >= 95) return 'S';
        if ($score >= 85) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 40) return 'D';
        return 'F';
    }
}