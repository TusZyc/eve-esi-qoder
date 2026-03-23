<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoutResult extends Model
{
    /**
     * 主键
     */
    protected $primaryKey = 'id';
    
    /**
     * 主键类型
     */
    public $incrementing = false;
    
    /**
     * 主键类型
     */
    protected $keyType = 'string';
    
    /**
     * 时间戳
     */
    public $timestamps = false;
    
    /**
     * 可填充的属性
     */
    protected $fillable = [
        'id',
        'items',
        'statistics',
        'ip_hash',
        'retention_hours',
        'created_at',
        'expires_at',
    ];
    
    /**
     * 类型转换
     */
    protected $casts = [
        'items' => 'array',
        'statistics' => 'array',
        'retention_hours' => 'integer',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    
    /**
     * 检查是否已过期
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false; // 永久保存
        }
        
        return $this->expires_at->isPast();
    }
    
    /**
     * 获取分享链接
     */
    public function getShareUrl(): string
    {
        return 'https://51-eve.online/scout/' . $this->id;
    }
    
    /**
     * 获取过期时间格式化
     */
    public function getExpiresAtFormatted(): string
    {
        if (!$this->expires_at) {
            return '永久保存';
        }
        
        return $this->expires_at->format('Y-m-d H:i:s');
    }
    
    /**
     * 获取创建时间格式化
     */
    public function getCreatedAtFormatted(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }
    
    /**
     * 获取保存时长描述
     */
    public function getRetentionLabel(): string
    {
        $hours = $this->retention_hours;
        
        return match ($hours) {
            2 => '2小时',
            12 => '12小时',
            48 => '2天',
            168 => '7天',
            0 => '永久',
            default => $hours . '小时',
        };
    }
}