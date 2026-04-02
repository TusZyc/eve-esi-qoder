<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Token 服务 - 统一的访问令牌获取服务
 * 
 * 解决 N+1 查询问题：
 * 同一个请求周期内，多次调用 getToken 只查询数据库一次
 * 
 * 使用方式：
 * - TokenService::getToken($characterId) - 静态调用
 * - app(TokenService::class)->getToken($characterId) - 实例调用
 */
class TokenService
{
    /**
     * 请求级别的 Token 缓存
     * 格式：[characterId => token]
     * 
     * 使用静态变量，同一个请求周期内只查询一次数据库
     */
    private static array $tokenCache = [];
    
    /**
     * 获取角色的访问令牌
     * 
     * 优先级：
     * 1. 请求缓存（已查询过的直接返回）
     * 2. 数据库查询（首次查询）
     * 
     * @param int $characterId EVE角色ID
     * @return string|null 访问令牌，失败返回 null
     */
    public static function getToken(int $characterId): ?string
    {
        // 1. 检查请求缓存（同一个请求周期内只查一次）
        if (isset(self::$tokenCache[$characterId])) {
            return self::$tokenCache[$characterId];
        }
        
        // 2. 从数据库查询
        $token = User::where('eve_character_id', $characterId)->value('access_token');
        
        if ($token === null) {
            Log::warning("TokenService: 角色 {$characterId} 的 Token 不存在或已失效");
            return null;
        }
        
        // 3. 存入请求缓存
        self::$tokenCache[$characterId] = $token;
        
        return $token;
    }
    
    /**
     * 批量获取多个角色的访问令牌
     * 
     * 一次性查询所有角色的 Token，避免循环查询
     * 
     * @param array $characterIds EVE角色ID数组
     * @return array [characterId => token]
     */
    public static function getTokens(array $characterIds): array
    {
        $tokens = [];
        $uncachedIds = [];
        
        // 1. 先从请求缓存获取
        foreach ($characterIds as $id) {
            if (isset(self::$tokenCache[$id])) {
                $tokens[$id] = self::$tokenCache[$id];
            } else {
                $uncachedIds[] = $id;
            }
        }
        
        // 2. 批量查询未缓存的（一次数据库查询）
        if (!empty($uncachedIds)) {
            $users = User::whereIn('eve_character_id', $uncachedIds)
                ->select(['eve_character_id', 'access_token'])
                ->get();
            
            foreach ($users as $user) {
                $tokens[$user->eve_character_id] = $user->access_token;
                // 存入请求缓存
                self::$tokenCache[$user->eve_character_id] = $user->access_token;
            }
            
            // 记录未找到的角色
            foreach ($uncachedIds as $id) {
                if (!isset($tokens[$id])) {
                    $tokens[$id] = null;
                }
            }
        }
        
        return $tokens;
    }
    
    /**
     * 清除请求缓存
     * 
     * 通常不需要手动调用，请求结束后自动失效
     * 但在 Token 刷新后需要清除旧缓存
     */
    public static function clearCache(int $characterId): void
    {
        unset(self::$tokenCache[$characterId]);
    }
    
    /**
     * 清除所有请求缓存
     */
    public static function clearAllCache(): void
    {
        self::$tokenCache = [];
    }
    
    /**
     * 更新请求缓存中的 Token
     * 
     * Token 刷新成功后调用此方法更新缓存
     * 
     * @param int $characterId EVE角色ID
     * @param string $token 新的访问令牌
     */
    public static function updateCache(int $characterId, string $token): void
    {
        self::$tokenCache[$characterId] = $token;
    }
}