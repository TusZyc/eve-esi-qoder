<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 可填充的属性
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'eve_character_id',
        'corporation_id',
        'alliance_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * 隐藏的属性
     */
    protected $hidden = [
        'password',
        'remember_token',
        'access_token',
        'refresh_token',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'token_expires_at' => 'datetime',
        'eve_character_id' => 'integer',
        'corporation_id' => 'integer',
        'alliance_id' => 'integer',
    ];

    /**
     * 检查是否有绑定的 EVE 角色
     */
    public function hasEveCharacter(): bool
    {
        return $this->eve_character_id !== null;
    }

    /**
     * 检查 Token 是否过期
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * 检查是否需要刷新 Token（提前 5 分钟刷新）
     */
    public function shouldRefreshToken(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->copy()->subMinutes(5)->isPast();
    }

    /**
     * 获取 Token 剩余有效时间（秒）
     */
    public function getTokenRemainingSeconds(): int
    {
        if (!$this->token_expires_at) {
            return 0;
        }

        return max(0, $this->token_expires_at->timestamp - time());
    }

    /**
     * 获取 Token 过期时间（格式化）
     */
    public function getTokenExpiryFormatted(): string
    {
        if (!$this->token_expires_at) {
            return '未知';
        }

        return $this->token_expires_at->format('Y-m-d H:i:s');
    }
}
