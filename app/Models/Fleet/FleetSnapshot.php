<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetSnapshot extends Model
{
    protected $fillable = [
        'operation_id',
        'snapshot_time',
        'member_count',
        'fleet_motd',
        'commander_system_id',
        'is_manual',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'snapshot_time' => 'datetime',
        'member_count' => 'integer',
        'commander_system_id' => 'integer',
        'is_manual' => 'boolean',
    ];

    /**
     * 关联行动
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(FleetOperation::class, 'operation_id');
    }

    /**
     * 成员快照详情
     */
    public function members(): HasMany
    {
        return $this->hasMany(FleetMemberSnapshot::class, 'snapshot_id');
    }

    /**
     * 获取指挥官所在星系名称
     */
    public function getCommanderSystemName(): string
    {
        if (!$this->commander_system_id) {
            return '未知';
        }
        
        // 尝试从成员快照中获取指挥官的星系名称
        $commanderMember = $this->members()
            ->where('character_id', $this->operation->commander_character_id)
            ->first();
        
        return $commanderMember?->solar_system_name ?? (string) $this->commander_system_id;
    }
}