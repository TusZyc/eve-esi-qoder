<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;
use App\Services\Killmail\BetaKbApiClient;

/**
 * KM 数据富化服务
 * 
 * 负责批量获取KM详情、名称解析等富化操作
 * 从 KillmailService 中提取，职责更清晰
 */
class KillmailEnrichService
{
    protected string $esiBaseUrl;
    protected string $datasource;
    protected EveDataService $eveData;
    protected BetaKbApiClient $kbClient;

    public function __construct(
        EveDataService $eveData,
        BetaKbApiClient $kbClient
    ) {
        $this->eveData = $eveData;
        $this->kbClient = $kbClient;
        $this->esiBaseUrl = rtrim(config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/'), '/') . '/';
        $this->datasource = config('esi.datasource', 'serenity');
    }

    // ========================================================
    // KM 列表富化 (批量 ESI 获取详情)
    // ========================================================

    /**
     * 批量富化 KM 列表数据
     * 
     * @param array $kills KM基础列表
     * @param callable|null $buildDetailResponse 构建详情响应的回调函数
     * @return array [富化后的列表, 详情映射]
     */
    public function enrichKillList(array $kills, ?callable $buildDetailResponse = null): array
    {
        if (empty($kills)) {
            return [[], []];
        }

        $enriched = [];
        $toFetch = [];
        $needHash = [];
        $detailsMap = [];

        foreach ($kills as $idx => $kill) {
            $killId = $kill['kill_id'];
            $cached = Cache::get("kb:kill:{$killId}");

            if ($cached) {
                $enriched[$idx] = $this->extractListDataFromDetail($kill, $cached);
                $detailsMap[$killId] = $cached;
            } elseif (!empty($kill['esi_hash'])) {
                $toFetch[$idx] = $kill;
            } else {
                $cachedHash = Cache::get("kb:esi_hash:{$killId}");
                if ($cachedHash) {
                    $kill['esi_hash'] = $cachedHash;
                    $toFetch[$idx] = $kill;
                } else {
                    $needHash[$idx] = $kill;
                }
            }
        }

        // 获取缺失的 ESI Hash（限制20条避免阻塞）
        foreach (array_slice($needHash, 0, 20, true) as $idx => $kill) {
            $hash = $this->kbClient->extractEsiHash($kill['kill_id']);
            if ($hash) {
                $kill['esi_hash'] = $hash;
                $toFetch[$idx] = $kill;
                unset($needHash[$idx]);
            }
        }

        // 无 Hash 的 KM 使用基础列表项
        foreach ($needHash as $idx => $kill) {
            $enriched[$idx] = $this->buildBasicListItem($kill);
        }

        // 批量获取 ESI 详情
        if (!empty($toFetch)) {
            $batchDetails = $this->batchFetchEsiKills($toFetch, $buildDetailResponse);

            foreach ($batchDetails as $idx => $detail) {
                if ($detail) {
                    $enriched[$idx] = $this->extractListDataFromDetail($toFetch[$idx], $detail);
                    $detailsMap[$toFetch[$idx]['kill_id']] = $detail;
                } else {
                    $enriched[$idx] = $this->buildBasicListItem($toFetch[$idx]);
                }
            }

            // 确保所有 KM 都有对应的富化项
            foreach ($toFetch as $idx => $kill) {
                if (!isset($enriched[$idx])) {
                    $enriched[$idx] = $this->buildBasicListItem($kill);
                }
            }
        }

        ksort($enriched);
        return [array_values($enriched), $detailsMap];
    }

    /**
     * 批量从 ESI 获取 KM 详情
     * 
     * @param array $kills 需要获取详情的KM列表
     * @param callable|null $buildDetailResponse 构建详情响应的回调函数
     * @return array 索引 => 详情数据
     */
    protected function batchFetchEsiKills(array $kills, ?callable $buildDetailResponse = null): array
    {
        $results = [];
        $chunks = array_chunk($kills, 20, true);

        foreach ($chunks as $chunk) {
            // 使用 HTTP Pool 并发请求
            $responses = Http::pool(function ($pool) use ($chunk) {
                foreach ($chunk as $idx => $kill) {
                    $url = $this->esiBaseUrl . "killmails/{$kill['kill_id']}/{$kill['esi_hash']}/";
                    $pool->as((string) $idx)->timeout(15)->get($url, [
                        'datasource' => $this->datasource,
                    ]);
                }
            });

            $allIds = [];
            $validResponses = [];

            // 收集有效的响应和需要解析的ID
            foreach ($responses as $idx => $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                    $esiData = $response->json();
                    if (!empty($esiData) && isset($esiData['victim'])) {
                        $validResponses[(int) $idx] = $esiData;
                        $allIds = array_merge($allIds, $this->collectIds($esiData));
                    }
                }
            }

            // 批量解析名称
            $names = $this->resolveNames(array_values(array_unique(array_filter($allIds))));

            // 构建详情并缓存
            foreach ($validResponses as $idx => $esiData) {
                $killId = $kills[$idx]['kill_id'] ?? 0;
                if ($killId > 0) {
                    // 如果提供了自定义的构建函数，使用它；否则使用默认构建
                    if ($buildDetailResponse) {
                        $detail = $buildDetailResponse($esiData, $names, $killId);
                    } else {
                        $detail = $this->buildDefaultKillDetailResponse($esiData, $names, $killId);
                    }
                    Cache::put("kb:kill:{$killId}", $detail, 3600);
                    $results[$idx] = $detail;
                }
            }
        }

        return $results;
    }

    /**
     * 从已有的详情缓存中提取列表需要的字段
     */
    public function extractListDataFromDetail(array $kill, array $detail): array
    {
        $victim = $detail['victim'] ?? [];
        $finalBlow = null;
        foreach ($detail['attackers'] ?? [] as $atk) {
            if (!empty($atk['final_blow'])) {
                $finalBlow = $atk;
                break;
            }
        }

        return [
            'kill_id' => $kill['kill_id'],
            'ship_type_id' => $victim['ship_type_id'] ?? ($kill['ship_type_id'] ?? null),
            'ship_name' => $victim['ship_name'] ?? ($kill['ship_name'] ?? null),
            'victim_id' => $victim['character_id'] ?? ($kill['victim_id'] ?? null),
            'victim_name' => $victim['character_name'] ?? ($kill['victim_name'] ?? null),
            'victim_corporation_id' => $victim['corporation_id'] ?? null,
            'victim_corp' => $victim['corporation_name'] ?? null,
            'victim_alliance' => $victim['alliance_name'] ?? null,
            'final_blow_name' => $finalBlow['character_name'] ?? null,
            'final_blow_corp' => $finalBlow['corporation_name'] ?? null,
            'final_blow_alliance' => $finalBlow['alliance_name'] ?? null,
            'final_blow_ship' => $finalBlow['ship_name'] ?? null,
            'system_id' => $detail['solar_system_id'] ?? ($kill['system_id'] ?? null),
            'system_name' => $detail['solar_system_name'] ?? ($kill['system_name'] ?? null),
            'system_sec' => $detail['system_sec'] ?? null,
            'region_name' => $detail['region_name'] ?? null,
            'kill_time' => $detail['kill_time'] ?? ($kill['kill_time'] ?? null),
            'total_value' => $kill['total_value'] ?? null,
            'esi_hash' => $kill['esi_hash'] ?? null,
            'attacker_count' => $detail['attacker_count'] ?? null,
        ];
    }

    /**
     * 构建基础列表项 (无 ESI 详情时)
     */
    public function buildBasicListItem(array $kill): array
    {
        return [
            'kill_id' => $kill['kill_id'],
            'ship_type_id' => $kill['ship_type_id'] ?? null,
            'ship_name' => $kill['ship_name'] ?? null,
            'victim_id' => $kill['victim_id'] ?? null,
            'victim_name' => $kill['victim_name'] ?? null,
            'victim_corporation_id' => null,
            'victim_corp' => null,
            'victim_alliance' => null,
            'final_blow_name' => null,
            'final_blow_corp' => null,
            'final_blow_alliance' => null,
            'final_blow_ship' => null,
            'system_id' => $kill['system_id'] ?? null,
            'system_name' => $kill['system_name'] ?? null,
            'system_sec' => null,
            'region_name' => null,
            'kill_time' => $kill['kill_time'] ?? null,
            'total_value' => $kill['total_value'] ?? null,
            'esi_hash' => $kill['esi_hash'] ?? null,
            'attacker_count' => null,
        ];
    }

    // ========================================================
    // 名称解析
    // ========================================================

    /**
     * 从 ESI 数据中收集所有需要解析名称的 ID
     */
    public function collectIds(array $esiData): array
    {
        $ids = [];

        if (!empty($esiData['solar_system_id'])) {
            $ids[] = (int) $esiData['solar_system_id'];
        }

        $victim = $esiData['victim'] ?? [];
        foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id'] as $field) {
            if (!empty($victim[$field])) {
                $ids[] = (int) $victim[$field];
            }
        }

        foreach ($victim['items'] ?? [] as $item) {
            if (!empty($item['item_type_id'])) {
                $ids[] = (int) $item['item_type_id'];
            }
        }

        foreach ($esiData['attackers'] ?? [] as $attacker) {
            foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id', 'weapon_type_id'] as $field) {
                if (!empty($attacker[$field])) {
                    $ids[] = (int) $attacker[$field];
                }
            }
        }

        foreach ($esiData['supporters'] ?? [] as $sup) {
            foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id', 'repairer_type_id'] as $field) {
                if (!empty($sup[$field])) {
                    $ids[] = (int) $sup[$field];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * 批量解析 ID 到名称
     */
    public function resolveNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $names = [];
        $missing = [];

        // 1. 优先从本地静态数据查找
        $localNames = EveDataService::getLocalNames($ids);
        foreach ($localNames as $id => $name) {
            $names[$id] = $name;
        }

        // 2. 从旧版内存数据库查找
        $itemDb = $this->eveData->getItemDatabase();
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;
            if (isset($itemDb[$id])) {
                $names[$id] = $itemDb[$id];
            }
        }

        // 3. 从缓存查找
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        // 4. 调用 ESI API 查询剩余的
        if (!empty($missing)) {
            $this->resolveNamesFromEsi($missing, $names);
        }

        return $names;
    }

    /**
     * 通过 ESI universe/names 批量解析名称
     */
    protected function resolveNamesFromEsi(array $ids, array &$names): void
    {
        $chunks = array_chunk($ids, 1000);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(10)->post(
                    $this->esiBaseUrl . 'universe/names/',
                    $chunk
                );

                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        if (isset($item['id']) && isset($item['name'])) {
                            $names[(int) $item['id']] = $item['name'];
                            Cache::put("eve_name_{$item['id']}", $item['name'], 86400);
                        }
                    }
                } else {
                    $this->resolveNamesFallback($chunk, $names);
                }
            } catch (\Exception $e) {
                Log::warning('ESI universe/names 批量查询失败: ' . $e->getMessage());
                $this->resolveNamesFallback($chunk, $names);
            }
        }
    }

    /**
     * 名称解析降级方案（逐个查询）
     */
    protected function resolveNamesFallback(array $ids, array &$names): void
    {
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;

            try {
                $response = Http::timeout(5)->post(
                    $this->esiBaseUrl . 'universe/names/',
                    [$id]
                );

                if ($response->ok()) {
                    $result = $response->json();
                    if (!empty($result[0]['name'])) {
                        $names[$id] = $result[0]['name'];
                        Cache::put("eve_name_{$id}", $result[0]['name'], 86400);
                        continue;
                    }
                }

                // 再尝试 universe/types 接口
                $response = Http::timeout(5)->get(
                    $this->esiBaseUrl . "universe/types/{$id}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if ($response->ok()) {
                    $data = $response->json();
                    if (!empty($data['name'])) {
                        $names[$id] = $data['name'];
                        Cache::put("eve_name_{$id}", $data['name'], 86400);
                    }
                }
            } catch (\Exception $e) {
                // 忽略单个失败
            }
        }
    }

    // ========================================================
    // 默认详情响应构建（简化版本，完整版本在 KillmailService）
    // ========================================================

    /**
     * 构建默认的 KM 详情响应
     * 注意：完整版本在 KillmailService::buildKillDetailResponse 中
     */
    protected function buildDefaultKillDetailResponse(array $esiData, array $names, int $killId): array
    {
        $victim = $esiData['victim'] ?? [];

        // 处理时间 - 转为北京时间
        $killTimeRaw = $esiData['killmail_time'] ?? null;
        $killTimeBj = null;
        if ($killTimeRaw) {
            try {
                $dt = new \DateTime($killTimeRaw, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('Asia/Shanghai'));
                $killTimeBj = $dt->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $killTimeBj = $killTimeRaw;
            }
        }

        // 处理攻击者
        $attackers = [];
        foreach ($esiData['attackers'] ?? [] as $atk) {
            $attackers[] = [
                'character_id' => $atk['character_id'] ?? null,
                'character_name' => isset($atk['character_id']) ? ($names[$atk['character_id']] ?? "未知#{$atk['character_id']}") : null,
                'corporation_id' => $atk['corporation_id'] ?? null,
                'corporation_name' => isset($atk['corporation_id']) ? ($names[$atk['corporation_id']] ?? null) : null,
                'alliance_id' => $atk['alliance_id'] ?? null,
                'alliance_name' => isset($atk['alliance_id']) ? ($names[$atk['alliance_id']] ?? null) : null,
                'ship_type_id' => $atk['ship_type_id'] ?? null,
                'ship_name' => isset($atk['ship_type_id']) ? ($names[$atk['ship_type_id']] ?? "未知#{$atk['ship_type_id']}") : null,
                'weapon_type_id' => $atk['weapon_type_id'] ?? null,
                'weapon_name' => isset($atk['weapon_type_id']) ? ($names[$atk['weapon_type_id']] ?? "未知#{$atk['weapon_type_id']}") : null,
                'damage_done' => $atk['damage_done'] ?? 0,
                'final_blow' => $atk['final_blow'] ?? false,
                'security_status' => round($atk['security_status'] ?? 0, 1),
            ];
        }

        // 按最后一击和伤害排序
        usort($attackers, function ($a, $b) {
            if ($a['final_blow'] && !$b['final_blow']) return -1;
            if (!$a['final_blow'] && $b['final_blow']) return 1;
            return $b['damage_done'] <=> $a['damage_done'];
        });

        $solarSystemId = $esiData['solar_system_id'] ?? null;

        return [
            'kill_id' => $killId,
            'kill_time' => $killTimeBj,
            'kill_time_raw' => $killTimeRaw,
            'solar_system_id' => $solarSystemId,
            'solar_system_name' => $solarSystemId ? ($names[$solarSystemId] ?? "未知#{$solarSystemId}") : null,
            'system_sec' => null, // 需要 getSystemInfo 调用
            'region_name' => null,
            'esi_verified' => true,
            'victim' => [
                'character_id' => $victim['character_id'] ?? null,
                'character_name' => isset($victim['character_id']) ? ($names[$victim['character_id']] ?? "未知#{$victim['character_id']}") : null,
                'corporation_id' => $victim['corporation_id'] ?? null,
                'corporation_name' => isset($victim['corporation_id']) ? ($names[$victim['corporation_id']] ?? null) : null,
                'alliance_id' => $victim['alliance_id'] ?? null,
                'alliance_name' => isset($victim['alliance_id']) ? ($names[$victim['alliance_id']] ?? null) : null,
                'ship_type_id' => $victim['ship_type_id'] ?? null,
                'ship_name' => isset($victim['ship_type_id']) ? ($names[$victim['ship_type_id']] ?? "未知#{$victim['ship_type_id']}") : null,
                'damage_taken' => $victim['damage_taken'] ?? 0,
                'position' => $victim['position'] ?? null,
                'items' => [], // 简化版本不处理物品
            ],
            'items_by_slot' => [],
            'attackers' => $attackers,
            'attacker_count' => count($attackers),
        ];
    }

    // ========================================================
    // Getters
    // ========================================================

    public function getEsiBaseUrl(): string
    {
        return $this->esiBaseUrl;
    }

    public function getDatasource(): string
    {
        return $this->datasource;
    }
}