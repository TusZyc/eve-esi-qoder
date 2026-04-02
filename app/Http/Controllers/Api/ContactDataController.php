<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Models\User;

class ContactDataController extends Controller
{
    // NPC agent character ID 范围（EVE Online 标准约定）
    private const NPC_CHARACTER_ID_MIN = 3000000;
    private const NPC_CHARACTER_ID_MAX = 3999999;

    /**
     * 获取角色联系人数据
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            // 获取联系人数据（支持分页）
            $contacts = Cache::remember("contacts_{$characterId}", 300, function () use ($baseUrl, $characterId) {
                $token = User::where('eve_character_id', $characterId)->value('access_token');
                if (!$token) return [];

                $allContacts = [];
                $page = 1;

                do {
                    $response = Http::withToken($token)
                        ->timeout(15)
                        ->get("{$baseUrl}characters/{$characterId}/contacts/", [
                            'datasource' => 'serenity',
                            'page' => $page
                        ]);

                    if (!$response->ok()) {
                        throw new \Exception('ESI request failed for contacts');
                    }

                    $data = $response->json();
                    if (empty($data)) {
                        break;
                    }

                    $allContacts = array_merge($allContacts, $data);
                    $page++;

                    // 检查是否还有更多页
                    $totalPages = (int) $response->header('X-Pages', 1);
                } while ($page <= $totalPages);

                return $allContacts;
            });

            if (empty($contacts)) {
                return response()->json([]);
            }

            // 收集所有联系人ID
            $contactIds = array_column($contacts, 'contact_id');
            $names = $this->getNames($contactIds);

            // 构建返回数据
            $result = [];
            foreach ($contacts as $contact) {
                $contactType = $contact['contact_type'] ?? 'character';
                
                // 过滤NPC阵营（faction类型）
                if ($contactType === 'faction') {
                    continue;
                }
                
                $contactId = $contact['contact_id'] ?? 0;
                
                // 过滤NPC agent（character_id 在 3000000-3999999 范围内）
                if ($contactType === 'character' && $this->isNpcCharacter($contactId)) {
                    continue;
                }
                
                $result[] = [
                    'contact_id' => $contactId,
                    'name' => $names[$contactId] ?? "联系人 #{$contactId}",
                    'contact_type' => $contactType,
                    'standing' => $contact['standing'] ?? 0,
                    'is_blocked' => $contact['is_blocked'] ?? false,
                    'is_watched' => $contact['is_watched'] ?? false,
                    'label_ids' => $contact['label_ids'] ?? []
                ];
            }

            // 按声望排序
            usort($result, fn($a, $b) => $b['standing'] <=> $a['standing']);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取联系人数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 判断是否为NPC角色
     */
    private function isNpcCharacter(int $characterId): bool
    {
        return $characterId >= self::NPC_CHARACTER_ID_MIN && $characterId <= self::NPC_CHARACTER_ID_MAX;
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

        foreach ($ids as $id) {
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        if (!empty($uncachedIds)) {
            // 分批处理，每批最多1000个
            foreach (array_chunk($uncachedIds, 1000) as $batch) {
                try {
                    $response = Http::timeout(15)
                        ->post("{$baseUrl}universe/names/", array_values(array_unique($batch)));
                    
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
        }

        return $names;
    }
}
