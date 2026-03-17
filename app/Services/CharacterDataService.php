<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 角色数据服务
 * 
 * 提供并行获取角色详细数据的功能，减少 API 调用延迟
 */
class CharacterDataService
{
    /**
     * 并行获取所有角色详细数据（属性、植入体、克隆、军团历史）
     *
     * @param string $charId 角色 ID
     * @param string $token 访问令牌
     * @return array 包含 attributes, implants, clones, history 的数组
     */
    public static function getAllCharacterData(string $charId, string $token): array
    {
        $cacheKey = CacheKeyService::characterData((int)$charId);
        
        return Cache::remember($cacheKey, CacheKeyService::TTL_SHORT, function () use ($charId, $token) {
            $baseUrl = config('esi.base_url');
            
            // 使用 Http::pool 并行发起 4 个 API 请求
            $responses = Http::pool(function ($pool) use ($charId, $token, $baseUrl) {
                // 角色属性（需要认证）
                $pool->as('attributes')
                    ->withToken($token)
                    ->timeout(10)
                    ->get($baseUrl . "characters/{$charId}/attributes/", ['datasource' => 'serenity']);
                
                // 当前植入体（需要认证）
                $pool->as('implants')
                    ->withToken($token)
                    ->timeout(10)
                    ->get($baseUrl . "characters/{$charId}/implants/", ['datasource' => 'serenity']);
                
                // 克隆体信息（需要认证）
                $pool->as('clones')
                    ->withToken($token)
                    ->timeout(10)
                    ->get($baseUrl . "characters/{$charId}/clones/", ['datasource' => 'serenity']);
                
                // 雇佣历史（公开端点，不需要认证）
                $pool->as('history')
                    ->timeout(10)
                    ->get($baseUrl . "characters/{$charId}/corporationhistory/", ['datasource' => 'serenity']);
            });
            
            $result = [];
            
            foreach (['attributes', 'implants', 'clones', 'history'] as $key) {
                $resp = $responses[$key] ?? null;
                
                if ($resp && $resp instanceof \Illuminate\Http\Client\Response && $resp->ok()) {
                    $result[$key] = $resp->json();
                } else {
                    $result[$key] = null;
                    
                    // 记录失败的请求
                    if ($resp instanceof \Illuminate\Http\Client\Response) {
                        Log::debug("[CharacterData] {$key} 请求失败", [
                            'char_id' => $charId,
                            'status' => $resp->status(),
                        ]);
                    }
                }
            }
            
            return $result;
        });
    }
    
    /**
     * 清除角色数据缓存
     *
     * @param int $charId 角色 ID
     */
    public static function clearCache(int $charId): void
    {
        Cache::forget(CacheKeyService::characterData($charId));
    }
    
    /**
     * 获取单个数据类型（带缓存）
     *
     * @param string $charId 角色 ID
     * @param string $token 访问令牌
     * @param string $type 数据类型: attributes, implants, clones, history
     * @return array|null
     */
    public static function getSingleData(string $charId, string $token, string $type): ?array
    {
        $allData = self::getAllCharacterData($charId, $token);
        return $allData[$type] ?? null;
    }
}
