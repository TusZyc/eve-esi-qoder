<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetOperation extends Model
{
    protected $fillable = [
        'fleet_id',
        'operation_name',
        'commander_character_id',
        'commander_name',
        'snapshot_interval',
        'auto_snapshot',
        'started_at',
        'ended_at',
        'status',
        'notes',
        'assembly_type',
        'assembly_type_other',
        'doctrine',
        'assembly_location',
        'fleet_commander',
    ];

    protected $casts = [
        'fleet_id' => 'integer',
        'commander_character_id' => 'integer',
        'snapshot_interval' => 'integer',
        'auto_snapshot' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'doctrine' => 'array',
    ];

    /**
     * 考核配置
     */
    public function config(): HasOne
    {
        return $this->hasOne(FleetOperationConfig::class, 'operation_id');
    }

    /**
     * 快照记录
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(FleetSnapshot::class, 'operation_id');
    }

    /**
     * 出勤汇总
     */
    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(FleetAttendanceSummary::class, 'operation_id');
    }

    /**
     * 是否正在进行中
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 获取行动时长（分钟）
     */
    public function getDurationMinutes(): int
    {
        $end = $this->ended_at ?? now();
        return (int) $this->started_at->diffInMinutes($end);
    }

    /**
     * 获取最新快照
     */
    public function latestSnapshot()
    {
        return $this->snapshots()->latest('snapshot_time')->first();
    }

    /**
     * 获取快照数量
     */
    public function getSnapshotCount(): int
    {
        return $this->snapshots()->count();
    }

    /**
     * 获取参与人数
     */
    public function getParticipantCount(): int
    {
        return $this->attendanceSummaries()->count();
    }
}