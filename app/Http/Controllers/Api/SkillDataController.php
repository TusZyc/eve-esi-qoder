<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;
use App\Models\User;
use App\Services\TokenService;

class SkillDataController extends Controller
{
    /**
     * 获取技能概览（总SP、未分配SP、训练剩余时间）
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $skillsData = $this->getSkillsData($user);
        $skillQueue = $this->getSkillQueue($user);

        $totalSP = $skillsData['total_sp'] ?? 0;
        $unallocatedSP = $skillsData['unallocated_sp'] ?? 0;

        $trainingTimeRemaining = 0;
        $now = time();
        if (!empty($skillQueue)) {
            $lastFinish = 0;
            foreach ($skillQueue as $item) {
                $f = isset($item['finish_date']) ? strtotime($item['finish_date']) : 0;
                if ($f > $lastFinish) $lastFinish = $f;
            }
            if ($lastFinish > $now) {
                $trainingTimeRemaining = $lastFinish - $now;
            }
        }

        return response()->json([
            'total_sp' => $totalSP,
            'unallocated_sp' => $unallocatedSP,
            'training_time_remaining' => $trainingTimeRemaining,
        ]);
    }

    /**
     * 获取技能队列（带技能名称）
     */
    public function queue(Request $request)
    {
        $user = $request->user();
        $skillQueue = $this->getSkillQueue($user);

        // 批量获取技能名称（避免 N+1 查询）
        $skillIds = array_filter(array_column($skillQueue, 'skill_id'));
        $skillNames = EveHelper::getNamesByIds($skillIds, 'skill');

        foreach ($skillQueue as &$item) {
            $skillId = $item['skill_id'] ?? 0;
            $item['skill_name'] = $skillNames[$skillId] ?? EveHelper::getNameById($skillId, 'skill');
        }

        return response()->json($skillQueue);
    }

    /**
     * 获取所有技能按分组（包括未学习的技能）
     */
    public function groups(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('esi.base_url');

        // 1. 获取技能类别(category_id=16)下的所有分组ID
        $allGroupIds = Cache::remember('eve_skill_category_groups', 86400, function () use ($baseUrl) {
            $resp = Http::timeout(10)->get($baseUrl . 'universe/categories/16/', ['datasource' => 'serenity']);
            if ($resp->ok()) {
                return $resp->json()['groups'] ?? [];
            }
            return [];
        });

        if (empty($allGroupIds)) {
            return response()->json([]);
        }

        // 2. 获取每个分组的信息（名称 + 包含的所有技能type_ids）
        $groupsInfo = [];
        $uncachedGroupIds = [];

        foreach ($allGroupIds as $groupId) {
            $cached = Cache::get("eve_skillgroup_full_{$groupId}");
            if ($cached !== null) {
                $groupsInfo[$groupId] = $cached;
            } else {
                $uncachedGroupIds[] = $groupId;
            }
        }

        if (!empty($uncachedGroupIds)) {
            foreach (array_chunk($uncachedGroupIds, 20) as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $groupId) {
                        $pool->as("group_{$groupId}")->timeout(10)
                            ->get($baseUrl . "universe/groups/{$groupId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                    }
                });
                foreach ($batch as $groupId) {
                    try {
                        $response = $responses["group_{$groupId}"] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $info = [
                                'name' => $data['name'] ?? '',
                                'types' => $data['types'] ?? [],
                            ];
                        } else {
                            $info = ['name' => '', 'types' => []];
                        }
                    } catch (\Exception $e) {
                        $info = ['name' => '', 'types' => []];
                    }
                    Cache::put("eve_skillgroup_full_{$groupId}", $info, 86400);
                    $groupsInfo[$groupId] = $info;
                }
            }
        }

        // 3. 获取角色已学技能，建立 skill_id => skill 映射
        $skillsData = $this->getSkillsData($user);
        $learnedSkills = [];
        if (!empty($skillsData['skills'])) {
            foreach ($skillsData['skills'] as $skill) {
                $learnedSkills[$skill['skill_id']] = $skill;
            }
        }

        // 4. 收集所有技能type_id，批量获取名称
        $allTypeIds = [];
        foreach ($groupsInfo as $info) {
            foreach ($info['types'] as $typeId) {
                $allTypeIds[] = $typeId;
            }
        }
        $allTypeIds = array_unique($allTypeIds);
        $skillNames = EveHelper::getNamesByIds($allTypeIds, 'skill');

        // 5. 构建按分组的完整技能列表
        $groups = [];
        foreach ($allGroupIds as $groupId) {
            $info = $groupsInfo[$groupId] ?? null;
            if (!$info || empty($info['types'])) continue;
            if (in_array($info['name'], ['虚构技能'])) continue;

            $skills = [];
            $learnedCount = 0;
            foreach ($info['types'] as $typeId) {
                $learned = isset($learnedSkills[$typeId]);
                $skill = [
                    'skill_id' => $typeId,
                    'skill_name' => $skillNames[$typeId] ?? EveHelper::getNameById($typeId, 'skill'),
                    'learned' => $learned,
                    'active_skill_level' => $learned ? ($learnedSkills[$typeId]['active_skill_level'] ?? 0) : 0,
                    'trained_skill_level' => $learned ? ($learnedSkills[$typeId]['trained_skill_level'] ?? 0) : 0,
                    'skillpoints_in_skill' => $learned ? ($learnedSkills[$typeId]['skillpoints_in_skill'] ?? 0) : 0,
                ];
                $skills[] = $skill;
                if ($learned) $learnedCount++;
            }

            // 排序：已学习的排前面，同状态按名称排序
            usort($skills, function ($a, $b) {
                if ($a['learned'] !== $b['learned']) return $b['learned'] ? 1 : -1;
                return strcmp($a['skill_name'], $b['skill_name']);
            });

            $groups[] = [
                'group_id' => $groupId,
                'group_name' => $info['name'],
                'total_count' => count($info['types']),
                'learned_count' => $learnedCount,
                'skills' => $skills,
            ];
        }

        usort($groups, fn($a, $b) => strcmp($a['group_name'], $b['group_name']));

        return response()->json($groups);
    }

    private function getSkillsData($user)
    {
        $characterId = $user->eve_character_id;
        return Cache::remember('skills_' . $characterId, 300, function () use ($characterId) {
            $token = TokenService::getToken($characterId);
            if (!$token) return null;

            $resp = Http::withToken($token)
                ->get(config('esi.base_url') . "characters/{$characterId}/skills/", [
                    'datasource' => 'serenity'
                ]);
            if (!$resp->ok()) throw new \Exception('ESI request failed');
            return $resp->json();
        });
    }

    private function getSkillQueue($user)
    {
        $characterId = $user->eve_character_id;
        return Cache::remember('skillqueue_' . $characterId, 60, function () use ($characterId) {
            $token = TokenService::getToken($characterId);
            if (!$token) return [];

            $resp = Http::withToken($token)
                ->get(config('esi.base_url') . "characters/{$characterId}/skillqueue/", [
                    'datasource' => 'serenity'
                ]);
            if (!$resp->ok()) throw new \Exception('ESI request failed');
            return $resp->json();
        });
    }

    private function getSkillGroups($skillIds)
    {
        $baseUrl = config('esi.base_url');
        $result = [];
        $typeGroupMap = [];
        $uncachedTypes = [];

        foreach ($skillIds as $typeId) {
            $cached = Cache::get("eve_type_{$typeId}");
            if ($cached !== null && isset($cached['group_id']) && $cached['group_id'] > 0) {
                $typeGroupMap[$typeId] = $cached['group_id'];
            } else {
                $uncachedTypes[] = $typeId;
            }
        }

        if (!empty($uncachedTypes)) {
            foreach (array_chunk($uncachedTypes, 20) as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $typeId) {
                        $pool->as("type_{$typeId}")->timeout(5)
                            ->get($baseUrl . "universe/types/{$typeId}/", ['datasource' => 'serenity']);
                    }
                });
                foreach ($batch as $typeId) {
                    try {
                        $response = $responses["type_{$typeId}"] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $rank = 1;
                            foreach ($data['dogma_attributes'] ?? [] as $attr) {
                                if (($attr['attribute_id'] ?? 0) == 275) {
                                    $rank = (int) ($attr['value'] ?? 1);
                                    break;
                                }
                            }
                            $detail = ['volume' => $data['volume'] ?? 0, 'group_id' => $data['group_id'] ?? 0, 'rank' => $rank];
                            $typeGroupMap[$typeId] = $detail['group_id'];
                        } else {
                            $detail = ['volume' => 0, 'group_id' => 0, 'rank' => 1];
                            $typeGroupMap[$typeId] = 0;
                        }
                    } catch (\Exception $e) {
                        $detail = ['volume' => 0, 'group_id' => 0, 'rank' => 1];
                        $typeGroupMap[$typeId] = 0;
                    }
                    Cache::put("eve_type_{$typeId}", $detail, 86400);
                }
            }
        }

        $groupIds = array_values(array_unique(array_filter($typeGroupMap)));
        $groupNames = [];
        $uncachedGroups = [];

        foreach ($groupIds as $groupId) {
            $cached = Cache::get("eve_groupinfo_zh_{$groupId}");
            if ($cached !== null) {
                $groupNames[$groupId] = $cached['name'] ?? '';
            } else {
                $uncachedGroups[] = $groupId;
            }
        }

        if (!empty($uncachedGroups)) {
            foreach (array_chunk($uncachedGroups, 20) as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl) {
                    foreach ($batch as $groupId) {
                        $pool->as("group_{$groupId}")->timeout(5)
                            ->get($baseUrl . "universe/groups/{$groupId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                    }
                });
                foreach ($batch as $groupId) {
                    try {
                        $response = $responses["group_{$groupId}"] ?? null;
                        if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                            $data = $response->json();
                            $groupInfo = ['name' => $data['name'] ?? '', 'category_id' => $data['category_id'] ?? 0];
                        } else {
                            $groupInfo = ['name' => '', 'category_id' => 0];
                        }
                    } catch (\Exception $e) {
                        $groupInfo = ['name' => '', 'category_id' => 0];
                    }
                    Cache::put("eve_groupinfo_zh_{$groupId}", $groupInfo, 86400);
                    $groupNames[$groupId] = $groupInfo['name'];
                }
            }
        }

        foreach ($skillIds as $typeId) {
            $groupId = $typeGroupMap[$typeId] ?? 0;
            $cached = Cache::get("eve_type_{$typeId}");
            $result[$typeId] = [
                'group_id' => $groupId,
                'group_name' => $groupNames[$groupId] ?? '其他',
                'rank' => $cached['rank'] ?? 1,
            ];
        }

        return $result;
    }
}
