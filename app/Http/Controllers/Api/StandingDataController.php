<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Models\User;
use App\Services\TokenService;

class StandingDataController extends Controller
{
    /**
     * 获取角色声望数据
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            // 获取声望数据
            $standings = Cache::remember("standings_{$characterId}", 300, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/standings/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed for standings');
                }
                return $response->json();
            });

            if (empty($standings)) {
                return response()->json([
                    'agents' => [],
                    'npc_corps' => [],
                    'factions' => []
                ]);
            }

            // 按类型分组
            $agents = [];
            $npcCorps = [];
            $factions = [];

            foreach ($standings as $standing) {
                $fromType = $standing['from_type'] ?? '';
                $fromId = $standing['from_id'] ?? 0;
                $standingValue = $standing['standing'] ?? 0;

                $item = [
                    'from_id' => $fromId,
                    'standing' => $standingValue,
                    'name' => '' // 稍后填充
                ];

                switch ($fromType) {
                    case 'agent':
                        $agents[] = $item;
                        break;
                    case 'npc_corp':
                        $npcCorps[] = $item;
                        break;
                    case 'faction':
                        $factions[] = $item;
                        break;
                }
            }

            // 批量获取名称
            $allIds = array_merge(
                array_column($agents, 'from_id'),
                array_column($npcCorps, 'from_id'),
                array_column($factions, 'from_id')
            );

            $names = $this->getNames($allIds);

            // 填充名称
            foreach ($agents as &$agent) {
                $agent['name'] = $names[$agent['from_id']] ?? "代理人 #{$agent['from_id']}";
            }
            foreach ($npcCorps as &$corp) {
                $corp['name'] = $names[$corp['from_id']] ?? "军团 #{$corp['from_id']}";
            }
            foreach ($factions as &$faction) {
                $faction['name'] = $names[$faction['from_id']] ?? "势力 #{$faction['from_id']}";
            }

            // 按声望值排序（高到低）
            usort($agents, fn($a, $b) => $b['standing'] <=> $a['standing']);
            usort($npcCorps, fn($a, $b) => $b['standing'] <=> $a['standing']);
            usort($factions, fn($a, $b) => $b['standing'] <=> $a['standing']);

            return response()->json([
                'agents' => $agents,
                'npc_corps' => $npcCorps,
                'factions' => $factions
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取声望数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 批量获取实体名称
     */
    private function getNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $baseUrl = config('esi.base_url');
        $names = [];
        $uncachedIds = [];

        // 先检查缓存
        foreach ($ids as $id) {
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        // 批量获取未缓存的名称
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
                // 忽略错误，使用默认名称
            }
        }

        return $names;
    }
}
