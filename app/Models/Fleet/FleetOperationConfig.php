<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetOperationConfig extends Model
{
    protected $fillable = [
        'operation_id',
        // 在队时长配置
        'duration_enabled',
        'duration_weight',
        'duration_min_percent',
        // 加入时间配置
        'join_time_enabled',
        'join_time_weight',
        'join_time_grace_minutes',
        // 舰船类型配置
        'ship_type_enabled',
        'ship_type_weight',
        'ship_type_required_ids',
        'ship_type_penalty_percent',
        // 到访星系配置
        'systems_enabled',
        'systems_weight',
        'systems_overlap_min_percent',
        // 离队距离配置
        'distance_enabled',
        'distance_weight',
        'distance_max_jumps',
        // 在站次数配置
        'in_station_enabled',
        'in_station_weight',
        'in_station_max_percent',
    ];

    protected $casts = [
        'operation_id' => 'integer',
        'duration_enabled' => 'boolean',
        'duration_weight' => 'decimal:2',
        'duration_min_percent' => 'integer',
        'join_time_enabled' => 'boolean',
        'join_time_weight' => 'decimal:2',
        'join_time_grace_minutes' => 'integer',
        'ship_type_enabled' => 'boolean',
        'ship_type_weight' => 'decimal:2',
        'ship_type_required_ids' => 'array',
        'ship_type_penalty_percent' => 'integer',
        'systems_enabled' => 'boolean',
        'systems_weight' => 'decimal:2',
        'systems_overlap_min_percent' => 'integer',
        'distance_enabled' => 'boolean',
        'distance_weight' => 'decimal:2',
        'distance_max_jumps' => 'integer',
        'in_station_enabled' => 'boolean',
        'in_station_weight' => 'decimal:2',
        'in_station_max_percent' => 'integer',
    ];

    /**
     * 关联行动
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(FleetOperation::class, 'operation_id');
    }

    /**
     * 获取总权重（应该为100）
     */
    public function getTotalWeight(): float
    {
        $total = 0;
        
        if ($this->duration_enabled) $total += $this->duration_weight;
        if ($this->join_time_enabled) $total += $this->join_time_weight;
        if ($this->ship_type_enabled) $total += $this->ship_type_weight;
        if ($this->systems_enabled) $total += $this->systems_weight;
        if ($this->distance_enabled) $total += $this->distance_weight;
        if ($this->in_station_enabled) $total += $this->in_station_weight;
        
        return $total;
    }

    /**
     * 验证权重是否有效（总和为100）
     */
    public function isWeightValid(): bool
    {
        return abs($this->getTotalWeight() - 100) < 0.01;
    }

    /**
     * 获取启用的维度列表
     */
    public function getEnabledDimensions(): array
    {
        $dimensions = [];
        
        if ($this->duration_enabled) $dimensions[] = 'duration';
        if ($this->join_time_enabled) $dimensions[] = 'join_time';
        if ($this->ship_type_enabled) $dimensions[] = 'ship_type';
        if ($this->systems_enabled) $dimensions[] = 'systems';
        if ($this->distance_enabled) $dimensions[] = 'distance';
        if ($this->in_station_enabled) $dimensions[] = 'in_station';
        
        return $dimensions;
    }

    /**
     * 获取默认配置
     */
    public static function getDefault(): self
    {
        return new self([
            'duration_enabled' => true,
            'duration_weight' => 20.00,
            'duration_min_percent' => 60,
            'join_time_enabled' => true,
            'join_time_weight' => 15.00,
            'join_time_grace_minutes' => 15,
            'ship_type_enabled' => true,
            'ship_type_weight' => 20.00,
            'ship_type_required_ids' => [],
            'ship_type_penalty_percent' => 50,
            'systems_enabled' => true,
            'systems_weight' => 20.00,
            'systems_overlap_min_percent' => 70,
            'distance_enabled' => true,
            'distance_weight' => 15.00,
            'distance_max_jumps' => 2,
            'in_station_enabled' => true,
            'in_station_weight' => 10.00,
            'in_station_max_percent' => 20,
        ]);
    }
}