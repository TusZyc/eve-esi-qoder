<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetAttendanceSummary extends Model
{
    protected $table = 'fleet_attendance_summary';

    protected $fillable = [
        'operation_id',
        'character_id',
        'character_name',
        'corporation_id',
        'corporation_name',
        // 基础统计
        'first_seen_at',
        'last_seen_at',
        'total_snapshots',
        'attendance_duration_minutes',
        'join_delay_minutes',
        // 舰船统计
        'ships_used',
        'primary_ship_id',
        'primary_ship_name',
        // 位置统计
        'systems_visited',
        'system_overlap_percent',
        'avg_jumps_from_commander',
        'jumps_qualified_percent',
        // 在站统计
        'in_station_snapshots',
        'in_station_percent',
        // 各维度得分
        'score_duration',
        'score_join_time',
        'score_ship_type',
        'score_systems',
        'score_distance',
        'score_in_station',
        // 最终评分
        'total_score',
        'grade',
        'is_full_participant',
        'notes',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'total_snapshots' => 'integer',
        'attendance_duration_minutes' => 'integer',
        'join_delay_minutes' => 'integer',
        'ships_used' => 'array',
        'primary_ship_id' => 'integer',
        'systems_visited' => 'array',
        'system_overlap_percent' => 'decimal:2',
        'avg_jumps_from_commander' => 'decimal:2',
        'jumps_qualified_percent' => 'decimal:2',
        'in_station_snapshots' => 'integer',
        'in_station_percent' => 'decimal:2',
        'score_duration' => 'decimal:2',
        'score_join_time' => 'decimal:2',
        'score_ship_type' => 'decimal:2',
        'score_systems' => 'decimal:2',
        'score_distance' => 'decimal:2',
        'score_in_station' => 'decimal:2',
        'total_score' => 'decimal:2',
        'is_full_participant' => 'boolean',
    ];

    /**
     * 关联行动
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(FleetOperation::class, 'operation_id');
    }

    /**
     * 获取等级对应的颜色
     */
    public function getGradeColor(): string
    {
        return match ($this->grade) {
            'S' => 'text-yellow-400',
            'A' => 'text-green-400',
            'B' => 'text-blue-400',
            'C' => 'text-gray-400',
            'D' => 'text-orange-400',
            'F' => 'text-red-400',
            default => 'text-gray-400',
        };
    }

    /**
     * 获取等级对应的背景色
     */
    public function getGradeBgColor(): string
    {
        return match ($this->grade) {
            'S' => 'bg-yellow-500/20',
            'A' => 'bg-green-500/20',
            'B' => 'bg-blue-500/20',
            'C' => 'bg-gray-500/20',
            'D' => 'bg-orange-500/20',
            'F' => 'bg-red-500/20',
            default => 'bg-gray-500/20',
        };
    }

    /**
     * 计算出勤时长占行动总时长的百分比
     */
    public function getAttendancePercent(): float
    {
        $operation = $this->operation;
        if (!$operation || $operation->getDurationMinutes() === 0) {
            return 0;
        }
        
        return round(($this->attendance_duration_minutes / $operation->getDurationMinutes()) * 100, 2);
    }

    /**
     * 获取各维度得分详情
     */
    public function getScoreDetails(): array
    {
        return [
            'duration' => [
                'name' => '在队时长',
                'score' => $this->score_duration,
                'enabled' => true,
            ],
            'join_time' => [
                'name' => '加入时间',
                'score' => $this->score_join_time,
                'enabled' => true,
            ],
            'ship_type' => [
                'name' => '舰船类型',
                'score' => $this->score_ship_type,
                'enabled' => true,
            ],
            'systems' => [
                'name' => '到访星系',
                'score' => $this->score_systems,
                'enabled' => true,
            ],
            'distance' => [
                'name' => '离队距离',
                'score' => $this->score_distance,
                'enabled' => true,
            ],
            'in_station' => [
                'name' => '在站次数',
                'score' => $this->score_in_station,
                'enabled' => true,
            ],
        ];
    }
}