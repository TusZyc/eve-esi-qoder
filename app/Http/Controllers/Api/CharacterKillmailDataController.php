<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Models\User;
use App\Services\TokenService;

class CharacterKillmailDataController extends Controller
{
    /**
     * 获取角色击毁报告列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            // 获取角色最近的击毁报告
            $killmails = Cache::remember("character_killmails_{$characterId}", 300, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/killmails/recent/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed for killmails');
                }
                return $response->json();
            });

            if (empty($killmails)) {
                return response()->json([]);
            }

            // 批量获取击毁报告详情
            $result = [];
            $killmailDetails = $this->getKillmailDetails($killmails, $characterId);

            foreach ($killmails as $km) {
                $killmailId = $km['killmail_id'] ?? 0;
                $killmailHash = $km['killmail_hash'] ?? '';
                
                $detail = $killmailDetails[$killmailId] ?? null;
                
                if ($detail) {
                    $victim = $detail['victim'] ?? [];
                    $attackers = $detail['attackers'] ?? [];
                    
                    // 判断是击杀还是被击杀
                    $victimId = $victim['character_id'] ?? 0;
                    $isVictim = $victimId == $characterId;
                    
                    // 获取最后一击
                    $finalBlow = null;
                    foreach ($attackers as $attacker) {
                        if ($attacker['final_blow'] ?? false) {
                            $finalBlow = $attacker;
                            break;
                        }
                    }
                    
                    $result[] = [
                        'killmail_id' => $killmailId,
                        'killmail_hash' => $killmailHash,
                        'killmail_time' => $detail['killmail_time'] ?? '',
                        'solar_system_id' => $detail['solar_system_id'] ?? 0,
                        'is_victim' => $isVictim,
                        'victim' => [
                            'character_id' => $victimId,
                            'character_name' => '', // 稍后填充
                            'corporation_id' => $victim['corporation_id'] ?? 0,
                            'alliance_id' => $victim['alliance_id'] ?? 0,
                            'ship_type_id' => $victim['ship_type_id'] ?? 0,
                            'ship_type_name' => '', // 稍后填充
                            'damage_taken' => $victim['damage_taken'] ?? 0,
                        ],
                        'attacker_count' => count($attackers),
                        'final_blow' => $finalBlow ? [
                            'character_id' => $finalBlow['character_id'] ?? 0,
                            'character_name' => '', // 稍后填充
                            'ship_type_id' => $finalBlow['ship_type_id'] ?? 0,
                            'ship_type_name' => '', // 稍后填充
                            'damage_done' => $finalBlow['damage_done'] ?? 0,
                        ] : null,
                        'zkb_url' => "https://zkillboard.com/kill/{$killmailId}/",
                    ];
                } else {
                    // 没有详情的情况
                    $result[] = [
                        'killmail_id' => $killmailId,
                        'killmail_hash' => $killmailHash,
                        'killmail_time' => '',
                        'solar_system_id' => 0,
                        'is_victim' => false,
                        'victim' => null,
                        'attacker_count' => 0,
                        'final_blow' => null,
                        'zkb_url' => "https://zkillboard.com/kill/{$killmailId}/",
                    ];
                }
            }

            // 获取所有需要的名称
            $this->enrichNames($result);

            // 按时间倒序排序
            usort($result, function($a, $b) {
                return strtotime($b['killmail_time'] ?: '1970-01-01') - strtotime($a['killmail_time'] ?: '1970-01-01');
            });

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取击毁报告失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 批量获取击毁报告详情
     */
    private function getKillmailDetails(array $killmails, int $characterId): array
    {
        $baseUrl = config('esi.base_url');
        $details = [];
        $toFetch = [];

        // 检查缓存
        foreach ($killmails as $km) {
            $killmailId = $km['killmail_id'] ?? 0;
            $cached = Cache::get("killmail_detail_{$killmailId}");
            if ($cached !== null) {
                $details[$killmailId] = $cached;
            } else {
                $toFetch[] = $km;
            }
        }

        // 批量获取未缓存的
        if (!empty($toFetch)) {
            // 使用 Http::pool 并发获取
            foreach (array_chunk($toFetch, 10) as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $km) {
                        $killmailId = $km['killmail_id'] ?? 0;
                        $killmailHash = $km['killmail_hash'] ?? '';
                        $pool->as("km_{$killmailId}")
                            ->timeout(10)
                            ->get("{$baseUrl}killmails/{$killmailId}/{$killmailHash}/", [
                                'datasource' => 'serenity'
                            ]);
                    }
                });

                foreach ($batch as $km) {
                    $killmailId = $km['killmail_id'] ?? 0;
                    try {
                        $response = $responses["km_{$killmailId}"] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            Cache::put("killmail_detail_{$killmailId}", $data, 86400);
                            $details[$killmailId] = $data;
                        }
                    } catch (\Exception $e) {
                        // 忽略错误
                    }
                }
            }
        }

        return $details;
    }

    /**
     * 填充名称信息
     */
    private function enrichNames(array &$result): void
    {
        $characterIds = [];
        $typeIds = [];
        $systemIds = [];

        foreach ($result as $km) {
            if ($km['victim']) {
                $characterIds[] = $km['victim']['character_id'] ?? 0;
                $typeIds[] = $km['victim']['ship_type_id'] ?? 0;
            }
            if ($km['final_blow']) {
                $characterIds[] = $km['final_blow']['character_id'] ?? 0;
                $typeIds[] = $km['final_blow']['ship_type_id'] ?? 0;
            }
            $systemIds[] = $km['solar_system_id'] ?? 0;
        }

        $characterIds = array_filter(array_unique($characterIds));
        $typeIds = array_filter(array_unique($typeIds));
        $systemIds = array_filter(array_unique($systemIds));

        // 获取名称
        $characterNames = $this->getNames($characterIds);
        $typeNames = EveHelper::getNamesByIds($typeIds, 'item');
        $systemNames = [];
        foreach ($systemIds as $id) {
            $systemNames[$id] = EveHelper::getNameById($id, 'system');
        }

        // 填充名称
        foreach ($result as &$km) {
            if ($km['victim']) {
                $km['victim']['character_name'] = $characterNames[$km['victim']['character_id'] ?? 0] ?? '';
                $km['victim']['ship_type_name'] = $typeNames[$km['victim']['ship_type_id'] ?? 0] ?? '';
            }
            if ($km['final_blow']) {
                $km['final_blow']['character_name'] = $characterNames[$km['final_blow']['character_id'] ?? 0] ?? '';
                $km['final_blow']['ship_type_name'] = $typeNames[$km['final_blow']['ship_type_id'] ?? 0] ?? '';
            }
            $km['solar_system_name'] = $systemNames[$km['solar_system_id'] ?? 0] ?? '';
        }
    }

    /**
     * 批量获取角色名称
     */
    private function getNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $baseUrl = config('esi.base_url');
        $names = [];
        $uncachedIds = [];

        foreach ($ids as $id) {
            if ($id <= 0) continue;
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        if (!empty($uncachedIds)) {
            try {
                $response = Http::timeout(15)
                    ->post("{$baseUrl}universe/names/", array_values(array_unique($uncachedIds)));
                
                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        $id = $item['id'] ?? 0;
                        $name = $item['name'] ?? '';
                        $names[$id] = $name;
                        Cache::put("eve_name_{$id}", $name, 86400);
                    }
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        return $names;
    }
}
