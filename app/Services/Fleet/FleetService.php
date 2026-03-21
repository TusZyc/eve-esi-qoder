<?php

namespace App\Services\Fleet;

use App\Models\Fleet\FleetOperation;
use App\Models\Fleet\FleetOperationConfig;
use App\Models\Fleet\FleetSnapshot;
use App\Models\Fleet\FleetAttendanceSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 舰队行动核心服务
 */
class FleetService
{
    protected FleetSnapshotService $snapshotService;
    protected AttendanceCalculator $calculator;

    public function __construct(FleetSnapshotService $snapshotService, AttendanceCalculator $calculator)
    {
        $this->snapshotService = $snapshotService;
        $this->calculator = $calculator;
    }

    /**
     * 创建新的行动
     *
     * @param User $commander 指挥官用户
     * @param array $data 行动数据
     * @return FleetOperation|null
     */
    public function createOperation(User $commander, array $data): ?FleetOperation
    {
        // 验证指挥官是否在舰队中
        $fleetInfo = $this->snapshotService->checkCharacterFleet(
            $commander->eve_character_id,
            $commander->access_token
        );

        if (!$fleetInfo) {
            Log::warning('[FleetService] 指挥官不在舰队中', [
                'character_id' => $commander->eve_character_id,
            ]);
            return null;
        }

        DB::beginTransaction();
        try {
            // 处理建制数据
            $doctrine = null;
            if (!empty($data['doctrine'])) {
                $doctrine = json_decode($data['doctrine'], true);
            }
            
            // 创建行动记录
            $operation = FleetOperation::create([
                'fleet_id' => $fleetInfo['fleet_id'],
                'operation_name' => $data['operation_name'],
                'commander_character_id' => $commander->eve_character_id,
                'commander_name' => $commander->name,
                'snapshot_interval' => $data['snapshot_interval'] ?? 60,
                'auto_snapshot' => $data['auto_snapshot'] ?? true,
                'started_at' => now(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'assembly_type' => $data['assembly_type'] ?? null,
                'assembly_type_other' => $data['assembly_type_other'] ?? null,
                'doctrine' => $doctrine,
                'assembly_location' => $data['assembly_location'] ?? null,
                'fleet_commander' => $data['fleet_commander'] ?? $commander->name,
            ]);

            // 创建考核配置
            $configData = $data['config'] ?? [];
            $config = FleetOperationConfig::create([
                'operation_id' => $operation->id,
                'duration_enabled' => $configData['duration_enabled'] ?? true,
                'duration_weight' => $configData['duration_weight'] ?? 20.00,
                'duration_min_percent' => $configData['duration_min_percent'] ?? 60,
                'join_time_enabled' => $configData['join_time_enabled'] ?? true,
                'join_time_weight' => $configData['join_time_weight'] ?? 15.00,
                'join_time_grace_minutes' => $configData['join_time_grace_minutes'] ?? 15,
                'ship_type_enabled' => $configData['ship_type_enabled'] ?? true,
                'ship_type_weight' => $configData['ship_type_weight'] ?? 20.00,
                'ship_type_required_ids' => $configData['ship_type_required_ids'] ?? [],
                'ship_type_penalty_percent' => $configData['ship_type_penalty_percent'] ?? 50,
                'systems_enabled' => $configData['systems_enabled'] ?? true,
                'systems_weight' => $configData['systems_weight'] ?? 20.00,
                'systems_overlap_min_percent' => $configData['systems_overlap_min_percent'] ?? 70,
                'distance_enabled' => $configData['distance_enabled'] ?? true,
                'distance_weight' => $configData['distance_weight'] ?? 15.00,
                'distance_max_jumps' => $configData['distance_max_jumps'] ?? 2,
                'in_station_enabled' => $configData['in_station_enabled'] ?? true,
                'in_station_weight' => $configData['in_station_weight'] ?? 10.00,
                'in_station_max_percent' => $configData['in_station_max_percent'] ?? 20,
            ]);

            DB::commit();

            Log::info('[FleetService] 行动创建成功', [
                'operation_id' => $operation->id,
                'fleet_id' => $operation->fleet_id,
                'commander' => $commander->name,
            ]);

            return $operation;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[FleetService] 创建行动失败', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 抓取快照（手动）
     *
     * @param FleetOperation $operation
     * @param User $commander
     * @return FleetSnapshot|null
     */
    public function captureSnapshot(FleetOperation $operation, User $commander): ?FleetSnapshot
    {
        if (!$operation->isActive()) {
            Log::warning('[FleetService] 行动已结束，无法抓取快照', [
                'operation_id' => $operation->id,
            ]);
            return null;
        }

        return $this->snapshotService->capture($operation, $commander->access_token, true);
    }

    /**
     * 自动抓取快照（定时任务调用）
     *
     * @param FleetOperation $operation
     * @return FleetSnapshot|null
     */
    public function autoCaptureSnapshot(FleetOperation $operation): ?FleetSnapshot
    {
        if (!$operation->isActive() || !$operation->auto_snapshot) {
            return null;
        }

        // 检查是否到了抓取时间
        $lastSnapshot = $operation->latestSnapshot();
        if ($lastSnapshot) {
            $nextCaptureTime = $lastSnapshot->snapshot_time->addSeconds($operation->snapshot_interval);
            if (now()->lt($nextCaptureTime)) {
                return null;
            }
        }

        // 获取指挥官的 Token
        $commander = User::where('eve_character_id', $operation->commander_character_id)->first();
        if (!$commander || !$commander->access_token) {
            Log::warning('[FleetService] 无法获取指挥官 Token', [
                'operation_id' => $operation->id,
                'commander_id' => $operation->commander_character_id,
            ]);
            return null;
        }

        return $this->snapshotService->capture($operation, $commander->access_token, false);
    }

    /**
     * 结束行动
     *
     * @param FleetOperation $operation
     * @return bool
     */
    public function endOperation(FleetOperation $operation): bool
    {
        if (!$operation->isActive()) {
            return false;
        }

        DB::beginTransaction();
        try {
            // 更新行动状态
            $operation->update([
                'status' => 'ended',
                'ended_at' => now(),
            ]);

            // 计算出勤评分
            $this->calculator->calculateAll($operation);

            DB::commit();

            Log::info('[FleetService] 行动已结束', [
                'operation_id' => $operation->id,
                'duration_minutes' => $operation->getDurationMinutes(),
                'participant_count' => $operation->getParticipantCount(),
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[FleetService] 结束行动失败', [
                'operation_id' => $operation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 获取进行中的行动列表
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveOperations()
    {
        return FleetOperation::where('status', 'active')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * 获取需要自动抓取的行动列表
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOperationsNeedingSnapshot()
    {
        return FleetOperation::where('status', 'active')
            ->where('auto_snapshot', true)
            ->get()
            ->filter(function ($operation) {
                $lastSnapshot = $operation->latestSnapshot();
                if (!$lastSnapshot) {
                    return true;
                }
                $nextCaptureTime = $lastSnapshot->snapshot_time->addSeconds($operation->snapshot_interval);
                return now()->gte($nextCaptureTime);
            });
    }

    /**
     * 获取行动的出勤报告
     *
     * @param FleetOperation $operation
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAttendanceReport(FleetOperation $operation)
    {
        return FleetAttendanceSummary::where('operation_id', $operation->id)
            ->orderByDesc('total_score')
            ->get();
    }

    /**
     * 获取评分统计摘要
     *
     * @param FleetOperation $operation
     * @return array
     */
    public function getScoreStatistics(FleetOperation $operation): array
    {
        $summaries = $operation->attendanceSummaries;

        return [
            'total_participants' => $summaries->count(),
            'average_score' => round($summaries->avg('total_score'), 2),
            'grade_distribution' => [
                'S' => $summaries->where('grade', 'S')->count(),
                'A' => $summaries->where('grade', 'A')->count(),
                'B' => $summaries->where('grade', 'B')->count(),
                'C' => $summaries->where('grade', 'C')->count(),
                'D' => $summaries->where('grade', 'D')->count(),
                'F' => $summaries->where('grade', 'F')->count(),
            ],
            'full_participants' => $summaries->where('is_full_participant', true)->count(),
        ];
    }

    /**
     * 检查用户的舰队状态
     *
     * @param User $user
     * @return array|null
     */
    public function checkUserFleetStatus(User $user): ?array
    {
        return $this->snapshotService->checkCharacterFleet(
            $user->eve_character_id,
            $user->access_token
        );
    }
}