<?php

namespace App\Services\Fleet;

use App\Models\Fleet\FleetOperation;
use App\Models\Fleet\FleetSnapshot;
use App\Models\Fleet\FleetMemberSnapshot;
use App\Models\User;
use App\Services\SystemDistanceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 舰队快照抓取服务
 */
class FleetSnapshotService
{
    protected string $esiBaseUrl;
    protected SystemDistanceService $distanceService;

    public function __construct(SystemDistanceService $distanceService)
    {
        $this->esiBaseUrl = config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/');
        $this->distanceService = $distanceService;
    }

    /**
     * 检查角色是否在舰队中
     *
     * @param int $characterId 角色 ID
     * @param string $token 访问令牌
     * @return array|null 返回舰队信息，null 表示不在舰队中
     */
    public function checkCharacterFleet(int $characterId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($this->esiBaseUrl . "characters/{$characterId}/fleet/", [
                    'datasource' => 'serenity',
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            // 404 表示不在舰队中
            if ($response->status() === 404) {
                return null;
            }

            Log::warning('[FleetSnapshot] 检查舰队状态失败', [
                'character_id' => $characterId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('[FleetSnapshot] 检查舰队状态异常', [
                'character_id' => $characterId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取舰队成员列表
     *
     * @param int $fleetId 舰队 ID
     * @param string $token 访问令牌
     * @return array|null
     */
    public function getFleetMembers(int $fleetId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get($this->esiBaseUrl . "fleets/{$fleetId}/members/", [
                    'datasource' => 'serenity',
                    'language' => 'zh',
                ]);

            if ($response->successful()) {
                $members = $response->json();
                
                // 记录完整的原始响应用于调试
                Log::info('[FleetSnapshot] 舰队成员原始响应', [
                    'fleet_id' => $fleetId,
                    'member_count' => count($members),
                    'first_member_keys' => !empty($members) ? array_keys($members[0]) : [],
                    'first_member' => !empty($members) ? $members[0] : [],
                ]);
                
                return $members;
            }

            Log::warning('[FleetSnapshot] 获取舰队成员失败', [
                'fleet_id' => $fleetId,
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('[FleetSnapshot] 获取舰队成员异常', [
                'fleet_id' => $fleetId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 获取舰队信息
     *
     * @param int $fleetId 舰队 ID
     * @param string $token 访问令牌
     * @return array|null
     */
    public function getFleetInfo(int $fleetId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($this->esiBaseUrl . "fleets/{$fleetId}/", [
                    'datasource' => 'serenity',
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('[FleetSnapshot] 获取舰队信息异常', [
                'fleet_id' => $fleetId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 抓取舰队快照
     *
     * @param FleetOperation $operation 行动记录
     * @param string $token 指挥官的访问令牌
     * @param bool $isManual 是否手动抓取
     * @return FleetSnapshot|null
     */
    public function capture(FleetOperation $operation, string $token, bool $isManual = false): ?FleetSnapshot
    {
        // 检查舰队状态
        $fleetInfo = $this->checkCharacterFleet($operation->commander_character_id, $token);
        
        if (!$fleetInfo) {
            Log::warning('[FleetSnapshot] 指挥官不在舰队中', [
                'operation_id' => $operation->id,
                'commander_id' => $operation->commander_character_id,
            ]);
            return null;
        }

        $fleetId = $fleetInfo['fleet_id'];

        // 获取成员列表
        $members = $this->getFleetMembers($fleetId, $token);
        
        if (!$members) {
            Log::warning('[FleetSnapshot] 无法获取成员列表', [
                'operation_id' => $operation->id,
                'fleet_id' => $fleetId,
            ]);
            return null;
        }

        // 获取舰队信息
        $fleetDetails = $this->getFleetInfo($fleetId, $token);

        // 找到指挥官的位置
        $commanderSystemId = null;
        foreach ($members as $member) {
            if ($member['character_id'] === $operation->commander_character_id) {
                $commanderSystemId = $member['solar_system_id'];
                break;
            }
        }

        // 创建快照记录
        $snapshot = FleetSnapshot::create([
            'operation_id' => $operation->id,
            'snapshot_time' => now(),
            'member_count' => count($members),
            'fleet_motd' => $fleetDetails['motd'] ?? null,
            'commander_system_id' => $commanderSystemId,
            'is_manual' => $isManual,
        ]);
        
        // 调试：记录原始成员数据
        Log::info('[FleetSnapshot] 原始成员数据', [
            'members_sample' => array_slice($members, 0, 2), // 只记录前2个避免日志过长
        ]);

        // 批量获取名称信息
        $enrichedMembers = $this->enrichMemberData($members);

        // 批量获取成员位置状态
        $enrichedMembers = $this->enrichMemberLocations($enrichedMembers, $token);

        // 计算与指挥官的距离
        $distances = $this->calculateDistances($commanderSystemId, $enrichedMembers);

        // 保存成员快照
        foreach ($enrichedMembers as $member) {
            $characterId = $member['character_id'];
            
            // 位置状态：in_station / in_space / unauthorized
            $locationStatus = $member['location_status'] ?? 'unknown';
            $inStation = $locationStatus === 'in_station';
            $stationId = $member['station_id'] ?? $member['structure_id'] ?? null;
            
            // 转换 join_time 从 UTC 到北京时间 (UTC+8)
            $joinTime = null;
            if (!empty($member['join_time'])) {
                try {
                    $joinTime = \Carbon\Carbon::parse($member['join_time'])->setTimezone('Asia/Shanghai');
                } catch (\Exception $e) {
                    $joinTime = $member['join_time'];
                }
            }
            
            FleetMemberSnapshot::create([
                'snapshot_id' => $snapshot->id,
                'character_id' => $characterId,
                'character_name' => $member['character_name'] ?? "Character {$characterId}",
                'corporation_id' => $member['corporation_id'] ?? null,
                'corporation_name' => $member['corporation_name'] ?? null,
                'solar_system_id' => $member['solar_system_id'],
                'solar_system_name' => $member['solar_system_name'] ?? null,
                'ship_type_id' => $member['ship_type_id'] ?? null,
                'ship_type_name' => $member['ship_type_name'] ?? null,
                'role' => $member['role'] ?? 'squad_member',
                'role_name' => $member['role_name'] ?? null,
                'wing_id' => $member['wing_id'] ?? null,
                'squad_id' => $member['squad_id'] ?? null,
                'join_time' => $joinTime,
                'takes_fleet_warp' => $member['takes_fleet_warp'] ?? true,
                'in_station' => $inStation,
                'station_id' => $stationId,
                'location_status' => $locationStatus,
                'jumps_from_commander' => $distances[$characterId] ?? null,
            ]);
        }

        Log::info('[FleetSnapshot] 快照抓取完成', [
            'operation_id' => $operation->id,
            'snapshot_id' => $snapshot->id,
            'member_count' => count($members),
            'is_manual' => $isManual,
        ]);

        return $snapshot;
    }

    /**
     * 补充成员数据（角色名、公司名、舰船名、星系名）
     */
    protected function enrichMemberData(array $members): array
    {
        // 收集需要查询的 ID
        $characterIds = [];
        $shipTypeIds = [];
        $systemIds = [];

        foreach ($members as $member) {
            $characterIds[] = $member['character_id'];
            if (!empty($member['ship_type_id'])) {
                $shipTypeIds[] = $member['ship_type_id'];
            }
            $systemIds[] = $member['solar_system_id'];
        }

        // 批量获取角色信息（包含名称和公司ID）
        $characterInfos = $this->batchGetCharacterInfo(array_unique($characterIds));
        
        // 收集公司ID
        $corpIds = [];
        foreach ($characterInfos as $info) {
            if (!empty($info['corporation_id'])) {
                $corpIds[] = $info['corporation_id'];
            }
        }
        
        // 批量获取公司名称
        $corpNames = $this->batchGetCorporationNames($corpIds);
        
        // 批量获取舰船和星系名称
        $shipNames = $this->batchGetTypeNames(array_unique($shipTypeIds));
        $systemNames = $this->batchGetSystemNames(array_unique($systemIds));

        // 补充数据
        foreach ($members as &$member) {
            $charId = $member['character_id'];
            
            // 角色名称和公司信息
            if (isset($characterInfos[$charId])) {
                $member['character_name'] = $characterInfos[$charId]['name'];
                $corpId = $characterInfos[$charId]['corporation_id'];
                $member['corporation_id'] = $corpId;
                if ($corpId && isset($corpNames[$corpId])) {
                    $member['corporation_name'] = $corpNames[$corpId];
                }
            }

            // 舰船名称
            if (!empty($member['ship_type_id']) && isset($shipNames[$member['ship_type_id']])) {
                $member['ship_type_name'] = $shipNames[$member['ship_type_id']];
            }

            // 星系名称
            if (isset($systemNames[$member['solar_system_id']])) {
                $member['solar_system_name'] = $systemNames[$member['solar_system_id']];
            }
        }

        return $members;
    }

    /**
     * 批量获取成员位置状态
     * 使用 /characters/{character_id}/location/ 接口
     * 返回状态：in_station / in_space / unauthorized
     */
    protected function enrichMemberLocations(array $members, string $commanderToken): array
    {
        // 收集所有成员的 character_id
        $characterIds = array_column($members, 'character_id');
        
        // 查找这些角色对应的用户账号（有授权token的）
        $usersWithTokens = User::whereIn('eve_character_id', $characterIds)
            ->whereNotNull('access_token')
            ->get()
            ->keyBy('eve_character_id');
        
        foreach ($members as &$member) {
            $charId = $member['character_id'];
            
            // 优先使用成员自己的token
            $token = $commanderToken;
            if (isset($usersWithTokens[$charId])) {
                $user = $usersWithTokens[$charId];
                // 检查token是否过期
                if ($user->token_expires_at && $user->token_expires_at->isFuture()) {
                    $token = $user->access_token;
                }
            }
            
            $location = $this->getCharacterLocation($charId, $token);

            if ($location === null) {
                // 接口调用失败或403
                $member['location_status'] = 'unauthorized';
            } elseif (isset($location['station_id']) || isset($location['structure_id'])) {
                // 在站内
                $member['location_status'] = 'in_station';
                $member['station_id'] = $location['station_id'] ?? null;
                $member['structure_id'] = $location['structure_id'] ?? null;
            } else {
                // 在太空中
                $member['location_status'] = 'in_space';
            }
        }

        return $members;
    }

    /**
     * 获取角色位置信息
     * 成功返回位置数据，403返回null
     */
    protected function getCharacterLocation(int $characterId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($this->esiBaseUrl . "characters/{$characterId}/location/", [
                    'datasource' => 'serenity',
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            // 403 表示没有权限查询该角色的位置
            if ($response->status() === 403) {
                Log::info('[FleetSnapshot] 角色位置查询未授权', [
                    'character_id' => $characterId,
                ]);
                return null;
            }

            Log::warning('[FleetSnapshot] 获取角色位置失败', [
                'character_id' => $characterId,
                'status' => $response->status(),
            ]);
        } catch (Exception $e) {
            Log::error('[FleetSnapshot] 获取角色位置异常', [
                'character_id' => $characterId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 批量获取实体名称
     */
    protected function batchGetNames(string $category, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $cacheKey = "fleet_names_{$category}_" . md5(implode(',', $ids));

        return Cache::remember($cacheKey, 3600, function () use ($category, $ids) {
            try {
                // ESI universe/names 接口需要 POST，ids 在 body，datasource 在 query
                $response = Http::timeout(15)
                    ->asJson()
                    ->post($this->esiBaseUrl . "universe/names/?datasource=serenity", $ids);

                if ($response->successful()) {
                    $result = [];
                    foreach ($response->json() as $item) {
                        if (isset($item['category']) && $item['category'] === $category) {
                            $result[$item['id']] = [
                                'name' => $item['name'],
                            ];
                        }
                    }
                    return $result;
                }
                
                Log::warning('[FleetSnapshot] 批量获取名称失败', [
                    'category' => $category,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (Exception $e) {
                Log::error('[FleetSnapshot] 批量获取名称异常', [
                    'category' => $category,
                    'error' => $e->getMessage(),
                ]);
            }

            return [];
        });
    }
    
    /**
     * 批量获取角色信息（包含公司ID）
     */
    protected function batchGetCharacterInfo(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }
        
        $result = [];
        
        foreach ($characterIds as $charId) {
            $cacheKey = "eve_char_info_{$charId}";
            
            $result[$charId] = Cache::remember($cacheKey, 3600, function () use ($charId) {
                try {
                    $response = Http::timeout(10)
                        ->get($this->esiBaseUrl . "characters/{$charId}/", [
                            'datasource' => 'serenity',
                        ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        return [
                            'name' => $data['name'] ?? "Character {$charId}",
                            'corporation_id' => $data['corporation_id'] ?? null,
                            'alliance_id' => $data['alliance_id'] ?? null,
                        ];
                    }
                } catch (Exception $e) {
                    Log::error('[FleetSnapshot] 获取角色信息失败', [
                        'character_id' => $charId,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                return [
                    'name' => "Character {$charId}",
                    'corporation_id' => null,
                ];
            });
        }
        
        return $result;
    }
    
    /**
     * 批量获取公司名称（使用 universe/names 接口获取中文名）
     */
    protected function batchGetCorporationNames(array $corpIds): array
    {
        if (empty($corpIds)) {
            return [];
        }
        
        $corpIds = array_filter(array_unique($corpIds));
        $result = [];
        $uncachedIds = [];
        
        // 先检查缓存
        foreach ($corpIds as $corpId) {
            $cacheKey = "eve_corp_name_zh_{$corpId}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $result[$corpId] = $cached;
            } else {
                $uncachedIds[] = $corpId;
            }
        }
        
        // 批量获取未缓存的名称
        if (!empty($uncachedIds)) {
            try {
                // 使用 universe/names 接口批量获取，支持 language=zh
                $response = Http::timeout(15)
                    ->asJson()
                    ->post($this->esiBaseUrl . "universe/names/?datasource=serenity&language=zh", $uncachedIds);
                
                if ($response->successful()) {
                    foreach ($response->json() as $item) {
                        if (isset($item['category']) && $item['category'] === 'corporation') {
                            $result[$item['id']] = $item['name'];
                            // 缓存 24 小时
                            Cache::put("eve_corp_name_zh_{$item['id']}", $item['name'], 86400);
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('[FleetSnapshot] 批量获取军团名称失败', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // 填充缺失的
        foreach ($corpIds as $corpId) {
            if (!isset($result[$corpId])) {
                $result[$corpId] = "Corporation {$corpId}";
            }
        }
        
        return $result;
    }

    /**
     * 批量获取舰船类型名称
     */
    protected function batchGetTypeNames(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $result = [];
        
        foreach ($typeIds as $typeId) {
            $cacheKey = "eve_type_name_{$typeId}";
            
            $result[$typeId] = Cache::remember($cacheKey, 86400, function () use ($typeId) {
                try {
                    $response = Http::timeout(10)
                        ->get($this->esiBaseUrl . "universe/types/{$typeId}/", [
                            'datasource' => 'serenity',
                            'language' => 'zh',
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['name'] ?? "Type {$typeId}";
                    }
                } catch (Exception $e) {
                    // Ignore
                }

                return "Type {$typeId}";
            });
        }

        return $result;
    }

    /**
     * 批量获取星系名称
     */
    protected function batchGetSystemNames(array $systemIds): array
    {
        if (empty($systemIds)) {
            return [];
        }

        $result = [];

        foreach ($systemIds as $systemId) {
            $result[$systemId] = $this->distanceService->getSystemName($systemId, 'zh');
        }

        return $result;
    }

    /**
     * 计算与指挥官的跳跃距离
     */
    protected function calculateDistances(?int $commanderSystemId, array $members): array
    {
        if (!$commanderSystemId) {
            return [];
        }

        $distances = [];
        $commanderSystemIds = [$commanderSystemId];

        // 批量计算距离
        foreach ($members as $member) {
            $memberSystemId = $member['solar_system_id'];
            
            if ($memberSystemId === $commanderSystemId) {
                $distances[$member['character_id']] = 0;
            } else {
                [$distance,] = $this->distanceService->getPathDistance($commanderSystemId, $memberSystemId);
                $distances[$member['character_id']] = $distance;
            }
        }

        return $distances;
    }
}