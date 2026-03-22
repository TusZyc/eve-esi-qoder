<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetMemberSnapshot extends Model
{
    protected $fillable = [
        'snapshot_id',
        'character_id',
        'character_name',
        'corporation_id',
        'corporation_name',
        'solar_system_id',
        'solar_system_name',
        'ship_type_id',
        'ship_type_name',
        'role',
        'role_name',
        'wing_id',
        'squad_id',
        'join_time',
        'takes_fleet_warp',
        'in_station',
        'station_id',
        'location_status',
        'jumps_from_commander',
    ];

    protected $casts = [
        'snapshot_id' => 'integer',
        'character_id' => 'integer',
        'corporation_id' => 'integer',
        'solar_system_id' => 'integer',
        'ship_type_id' => 'integer',
        'wing_id' => 'integer',
        'squad_id' => 'integer',
        'join_time' => 'datetime',
        'takes_fleet_warp' => 'boolean',
        'in_station' => 'boolean',
        'station_id' => 'integer',
        'jumps_from_commander' => 'integer',
    ];

    /**
     * 关联快照
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(FleetSnapshot::class, 'snapshot_id');
    }

    /**
     * 是否是舰队指挥
     */
    public function isCommander(): bool
    {
        return in_array($this->role, ['fleet_commander']);
    }

    /**
     * 是否是联队长
     */
    public function isWingCommander(): bool
    {
        return in_array($this->role, ['wing_commander']);
    }

    /**
     * 是否是小队长
     */
    public function isSquadCommander(): bool
    {
        return in_array($this->role, ['squad_commander']);
    }

    /**
     * 获取角色显示名称
     */
    public function getRoleDisplayName(): string
    {
        // 优先使用 ESI 返回的 role_name
        if (!empty($this->role_name)) {
            return $this->role_name;
        }
        
        // 回退到翻译
        return match ($this->role) {
            'fleet_commander' => '舰队指挥',
            'wing_commander' => '联队长',
            'squad_commander' => '小队长',
            'squad_member' => '成员',
            default => $this->role ?? '成员',
        };
    }
}