<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Helpers\EveHelper;
use App\Services\CacheKeyService;
use App\Services\ApiErrorHandler;
use App\Exceptions\EveApiException;

/**
 * Dashboard 数据 API 控制器
 * 
 * 提供异步数据加载接口，支持前端分模块加载
 */
class DashboardDataController extends Controller
{
    /**
     * 获取服务器状态
     * 
     * EVE 服务器状态说明：
     * - 在线：API 正常响应，玩家可登录
     * - 调试中：API 正常响应，但服务器处于维护状态
     * - 重启中：API 无法连接（连接超时），服务器不在线
     * - VIP 状态：vip=true，只有 GM 能进入，用于维护后测试
     */
    public function serverStatus()
    {
        // 成功响应缓存 5 分钟，错误响应缓存 60 秒
        $cacheKey = CacheKeyService::serverStatus();
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return response()->json($cached['data'], $cached['status']);
        }
        
        try {
            $statusUrl = config('esi.base_url') . 'status/';
            $statusResponse = Http::timeout(5)->get($statusUrl);
            
            if ($statusResponse->ok()) {
                $data = $statusResponse->json();
                
                // 检查 VIP 状态
                $isVip = isset($data['vip']) && $data['vip'] === true;
                
                // 检查是否在线玩家数为 0（可能刚开服或维护中）
                $players = $data['players'] ?? 0;
                
                Log::info('服务器状态', [
                    'players' => $players,
                    'version' => $data['server_version'] ?? 'N/A',
                    'vip' => $isVip,
                ]);
                
                $response = [
                    'success' => true,
                    'data' => [
                        'players' => $players,
                        'server_version' => $data['server_version'] ?? 'N/A',
                        'vip' => $isVip,
                        'start_time' => $data['start_time'] ?? null,
                        'status_text' => $this->getStatusText($isVip, $players),
                        'is_online' => true,
                        'is_maintenance' => $players === 0 && !$isVip,
                    ],
                ];
                
                // 成功响应缓存 5 分钟
                Cache::put($cacheKey, ['data' => $response, 'status' => 200], CacheKeyService::TTL_SHORT);
                
                return response()->json($response);
            } elseif ($statusResponse->status() === 503) {
                Log::warning('服务器维护中 (HTTP 503)');
                $response = [
                    'success' => false,
                    'error' => 'server_maintenance',
                    'message' => '服务器维护中',
                    'detail' => 'API 可访问，但服务器处于维护状态',
                ];
                
                // 错误响应缓存 60 秒
                Cache::put($cacheKey, ['data' => $response, 'status' => 503], 60);
                
                return response()->json($response, 503);
            } elseif ($statusResponse->status() === 504) {
                Log::warning('服务器网关超时 (HTTP 504)');
                $response = [
                    'success' => false,
                    'error' => 'gateway_timeout',
                    'message' => '网关超时',
                ];
                
                Cache::put($cacheKey, ['data' => $response, 'status' => 504], 60);
                
                return response()->json($response, 504);
            }
            
            $response = [
                'success' => false,
                'error' => 'http_error',
                'message' => 'HTTP ' . $statusResponse->status(),
            ];
            
            Cache::put($cacheKey, ['data' => $response, 'status' => $statusResponse->status()], 60);
            
            return response()->json($response, $statusResponse->status());
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // 连接超时 - 服务器不在线，正在重启
            Log::warning('服务器连接失败，正在重启中：' . $e->getMessage());
            $response = [
                'success' => false,
                'error' => 'server_offline',
                'message' => '服务器不在线',
                'detail' => '服务器正在重启中，请等待开服',
            ];
            
            Cache::put($cacheKey, ['data' => $response, 'status' => 503], 60);
            
            return response()->json($response, 503);
        } catch (\Exception $e) {
            Log::error('服务器状态请求异常：' . $e->getMessage());
            $response = [
                'success' => false,
                'error' => 'unknown_error',
                'message' => '未知错误',
            ];
            
            Cache::put($cacheKey, ['data' => $response, 'status' => 500], 60);
            
            return response()->json($response, 500);
        }
    }
    
    /**
     * 获取角色信息（军团、联盟等）
     */
    public function characterInfo(Request $request)
    {
        try {
            $user = ApiErrorHandler::requireAuth($request);
            
            Log::info('👤 [API] 请求角色信息', [
                'user_id' => $user->id,
                'character_id' => $user->eve_character_id,
            ]);
            Log::info('👤 [API] 请求 EVE API 角色信息');
            
            // 获取角色公开信息（包含军团和联盟 ID）
            $characterResponse = Http::timeout(10)
                ->withToken($user->access_token)
                ->get(config('esi.base_url') . "characters/{$user->eve_character_id}/");
            
            if ($characterResponse->failed()) {
                Log::error('👤 [API] 角色信息获取失败', [
                    'status' => $characterResponse->status(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'eve_api_error',
                    'message' => 'EVE API 错误：HTTP ' . $characterResponse->status(),
                ], $characterResponse->status());
            }
            
            $character = $characterResponse->json();
            
            $corporationId = $character['corporation_id'] ?? null;
            $allianceId = $character['alliance_id'] ?? null; // 可能为 null（无联盟）
            
            Log::info('👤 [API] 角色信息获取成功', [
                'corporation_id' => $corporationId,
                'alliance_id' => $allianceId,
            ]);
            
            // 批量查询军团和联盟名称
            $names = [];
            $idsToQuery = [];
            
            if ($corporationId) {
                $idsToQuery[] = $corporationId;
            }
            if ($allianceId) {
                $idsToQuery[] = $allianceId;
            }
            
            if (!empty($idsToQuery)) {
                $namesResponse = Http::timeout(10)
                    ->post(config('esi.base_url') . 'universe/names/', $idsToQuery);
                
                if ($namesResponse->ok()) {
                    $namesResult = $namesResponse->json();
                    foreach ($namesResult as $item) {
                        $names[$item['id']] = $item['name'];
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'character_id' => $user->eve_character_id,
                    'character_name' => $character['name'] ?? $user->name,
                    'corporation_id' => $corporationId,
                    'corporation_name' => $names[$corporationId] ?? '未知军团',
                    'alliance_id' => $allianceId,
                    'alliance_name' => $allianceId ? ($names[$allianceId] ?? '未知联盟') : null,
                    'has_alliance' => $allianceId !== null,
                ],
            ]);
            
        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('👤 [API] 角色信息连接失败：' . $e->getMessage());
            return ApiErrorHandler::error('connection_timeout', '连接超时，EVE API 可能不可用', 503);
        } catch (\Exception $e) {
            Log::error('👤 [API] 角色信息请求异常：' . $e->getMessage());
            return ApiErrorHandler::error('unknown_error', '未知错误', 500);
        }
    }
    
    /**
     * 获取状态文本描述
     */
    private function getStatusText(bool $isVip, int $players): string
    {
        if ($isVip) {
            return 'VIP 模式（GM 测试中，未正式开服）';
        }
        
        // 玩家数为 0 但服务器在线，说明处于调试状态
        if ($players === 0) {
            return '调试中（服务器已启动，当前无玩家在线）';
        }
        
        return '已开服';
    }
    
    /**
     * 获取技能数据
     */
    public function skills(Request $request)
    {
        try {
            $user = ApiErrorHandler::requireAuth($request);
            
            Log::info('📚 [API] 请求技能数据', [
                'user_id' => $user->id,
                'character_id' => $user->eve_character_id,
                'has_token' => !empty($user->access_token),
                'token_expired' => $user->isTokenExpired(),
            ]);
            Log::info('📚 [API] 请求 EVE API 技能数据');
            
            $skillsResponse = Http::timeout(10)
                ->withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skills/');
            
            Log::info('📚 [API] EVE API 响应', ['status' => $skillsResponse->status()]);
            
            if ($skillsResponse->ok()) {
                $data = $skillsResponse->json();
                Log::info('📚 [API] 技能数据获取成功', [
                    'total_sp' => $data['total_sp'] ?? 0,
                    'skills_count' => count($data['skills'] ?? []),
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_sp' => $data['total_sp'] ?? 0,
                        'unallocated_sp' => $data['unallocated_sp'] ?? 0,
                        'skills' => $data['skills'] ?? [],
                    ],
                ]);
            }
            
            // 401/403 表示 Token 过期或权限不足
            if ($skillsResponse->status() === 401 || $skillsResponse->status() === 403) {
                Log::error('📚 [API] Token 已过期或权限不足', [
                    'status' => $skillsResponse->status(),
                    'body' => $skillsResponse->body(),
                ]);
                throw EveApiException::tokenExpired('Token 已过期，请刷新 Token 或重新授权');
            }
            
            Log::warning('📚 [API] 技能数据获取失败', ['status' => $skillsResponse->status()]);
            return ApiErrorHandler::error('http_error', 'HTTP ' . $skillsResponse->status(), $skillsResponse->status());
            
        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('📚 [API] 技能数据连接失败：' . $e->getMessage());
            return ApiErrorHandler::error('connection_timeout', '连接超时，EVE API 可能不可用', 503);
        } catch (\Exception $e) {
            Log::error('📚 [API] 技能数据请求异常：' . $e->getMessage());
            return ApiErrorHandler::error('unknown_error', '未知错误', 500);
        }
    }
    
    /**
     * 获取技能队列
     */
    public function skillQueue(Request $request)
    {
        $user = $request->user();
        
        if (!$user->eve_character_id) {
            return response()->json([
                'success' => false,
                'error' => 'no_character',
                'message' => '未绑定 EVE 角色',
            ], 400);
        }
        
        try {
            $queueResponse = Http::timeout(10)
                ->withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skillqueue/');
            
            if ($queueResponse->ok()) {
                $queue = $queueResponse->json();
                
                // 为技能队列添加中文名称
                foreach ($queue as &$queueItem) {
                    $skillId = $queueItem['skill_id'] ?? 0;
                    try {
                        $queueItem['skill_name'] = EveHelper::getNameById($skillId, 'skill');
                    } catch (\Exception $e) {
                        $queueItem['skill_name'] = '技能 ID: ' . $skillId;
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $queue,
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'http_error',
                'message' => 'HTTP ' . $queueResponse->status(),
            ], $queueResponse->status());
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('技能队列连接失败：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'connection_timeout',
                'message' => '连接超时',
            ], 503);
        } catch (\Exception $e) {
            Log::error('技能队列请求异常：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'unknown_error',
                'message' => '未知错误',
            ], 500);
        }
    }
}
