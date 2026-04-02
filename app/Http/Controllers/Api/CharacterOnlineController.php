<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;

/**
 * 角色在线状态 API 控制器
 */
class CharacterOnlineController extends Controller
{
    /**
     * 获取角色在线状态
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        Log::info('🟢 [API] 请求角色在线状态', [
            'user_id' => $user->id ?? 'null',
            'character_id' => $user->eve_character_id ?? 'null',
        ]);
        
        if (!$user || !$user->eve_character_id) {
            return response()->json([
                'success' => false,
                'error' => 'unauthorized',
                'message' => '未授权，请重新登录',
            ], 401);
        }
        
        if (empty($user->access_token)) {
            return response()->json([
                'success' => false,
                'error' => 'no_token',
                'message' => '缺少访问令牌',
            ], 401);
        }
        
        try {
            // 获取角色在线状态
            $onlineResponse = Http::timeout(10)
                ->withToken($user->access_token)
                ->get(config('esi.base_url') . "characters/{$user->eve_character_id}/online/");
            
            if ($onlineResponse->failed()) {
                Log::error('🟢 [API] 在线状态获取失败', [
                    'status' => $onlineResponse->status(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'eve_api_error',
                    'message' => 'EVE API 错误',
                ], $onlineResponse->status());
            }
            
            $online = $onlineResponse->json();
            
            // 在线状态
            $isOnline = $online['online'] ?? false;
            $shipTypeId = $online['ship_type_id'] ?? null;
            $startTime = $online['start_time'] ?? null;
            
            Log::info('🟢 [API] 在线状态', [
                'is_online' => $isOnline,
                'ship_type_id' => $shipTypeId,
            ]);
            
            // 如果在线，查询舰船名称
            $shipName = null;
            if ($isOnline && $shipTypeId) {
                // 先查本地数据
                $shipName = EveDataService::getLocalItemName($shipTypeId);
                
                // 本地没有，调用 ESI API 兜底
                if (!$shipName) {
                    try {
                        $shipResponse = Http::timeout(10)
                            ->get(config('esi.base_url') . "universe/types/{$shipTypeId}/", [
                                'datasource' => 'serenity'
                            ]);
                        
                        if ($shipResponse->ok()) {
                            $shipData = $shipResponse->json();
                            $shipName = $shipData['name'] ?? null;
                        }
                    } catch (\Exception $e) {
                        Log::warning('🟢 [API] 舰船名称查询失败：' . $e->getMessage());
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_online' => $isOnline,
                    'ship_type_id' => $shipTypeId,
                    'ship_name' => $shipName,
                    'start_time' => $startTime,
                    'display_text' => $isOnline ? ($shipName ?? '在线') : '离线',
                ],
            ]);
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('🟢 [API] 在线状态连接失败：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'connection_timeout',
                'message' => '连接超时',
            ], 503);
        } catch (\Exception $e) {
            Log::error('🟢 [API] 在线状态请求异常：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'unknown_error',
                'message' => '未知错误',
            ], 500);
        }
    }
}
