<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;
use App\Services\Killmail\ProtobufCodec;
use App\Services\Killmail\BetaKbApiClient;
use App\Services\Killmail\KillmailSearchService;

/**
 * Killmail 服务 - 门面模式
 * 
 * 委托子服务处理具体功能：
 * - ProtobufCodec: Protobuf 编解码
 * - BetaKbApiClient: Beta KB API 交互
 * - KillmailSearchService: 搜索功能
 * 
 * 本类保留 KM 列表处理、详情获取和高级搜索等核心业务逻辑
 */
class KillmailService
{
    protected string $esiBaseUrl;
    protected string $datasource;
    protected EveDataService $eveData;
    protected ProtobufCodec $codec;
    protected BetaKbApiClient $kbClient;
    protected KillmailSearchService $searchService;

    public function __construct(
        EveDataService $eveData,
        ProtobufCodec $codec,
        BetaKbApiClient $kbClient,
        KillmailSearchService $searchService
    ) {
        $this->eveData = $eveData;
        $this->codec = $codec;
        $this->kbClient = $kbClient;
        $this->searchService = $searchService;
        $this->esiBaseUrl = rtrim(config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/'), '/') . '/';
        $this->datasource = config('esi.datasource', 'serenity');
    }

    // ========================================================
    // 搜索相关 - 委托给 KillmailSearchService
    // ========================================================

    /**
     * 统一自动补全接口
     */
    public function searchAutocomplete(string $query, string $type): array
    {
        return $this->searchService->searchAutocomplete($query, $type);
    }

    /**
     * 搜索角色 (保留旧接口兼容)
     */
    public function searchCharacter(string $query): array
    {
        return $this->searchService->searchCharacter($query);
    }

    // ========================================================
    // 获取实体 KM 列表
    // ========================================================

    /**
     * 通用的 KM 列表获取
     */
    public function getEntityKills(string $entityType, int $entityId): array
    {
        $cacheKey = "kb:entity:{$entityType}:{$entityId}";

        return Cache::remember($cacheKey, 600, function () use ($entityType, $entityId) {
            try {
                $kills = $this->kbClient->fetchBetaEntityKillList($entityType, $entityId);
                if (!empty($kills)) {
                    return $kills;
                }
            } catch (\Exception $e) {
                Log::debug("Beta KB entity list failed ({$entityType} {$entityId}): " . $e->getMessage());
            }

            // 降级: 仅 pilot 有旧 KB HTML 备选
            if ($entityType === 'pilot') {
                try {
                    return $this->kbClient->fetchKbHtmlKillList($entityId);
                } catch (\Exception $e) {
                    Log::debug("旧 KB kill list 也失败 (pilot {$entityId}): " . $e->getMessage());
                }
            }

            return [];
        });
    }

    /**
     * 保留旧接口兼容
     */
    public function getPilotKills(int $pilotId, string $mode = 'kills'): array
    {
        return $this->getEntityKills('pilot', $pilotId);
    }

    // ========================================================
    // 高级搜索 (带过滤 + 富化)
    // ========================================================

    /**
     * 高级搜索
     */
    public function advancedSearch(array $params): array
    {
        $entityType = $params['entity_type'] ?? null;
        $entityId = $params['entity_id'] ?? null;
        $involvement = $params['involvement'] ?? null;
        $shipId = $params['ship_id'] ?? null;
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        if (!$entityType || !$entityId) {
            // 仅时间搜索: 直接用 Search API 的日期范围
            if ($timeStart || $timeEnd) {
                $searchParams = [];
                if ($shipId) $searchParams['types'] = [(int) $shipId];
                if ($systemId) $searchParams['systems'] = [(int) $systemId];
                if ($timeStart) $searchParams['start_date'] = $timeStart;
                if ($timeEnd) $searchParams['end_date'] = $timeEnd;

                if (empty($searchParams)) {
                    throw new \Exception('缺少搜索条件');
                }

                $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);
                if (!empty($kills)) {
                    $kills = array_slice($kills, 0, 50);
                    [$enriched, $detailsMap] = $this->enrichKillList($kills);
                    return $enriched;
                }
                return [];
            }
            throw new \Exception('缺少搜索条件');
        }

        $chartypeMap = ['victim' => 'lost', 'finalblow' => 'win', 'attacker' => 'atk'];

        // 判断是否为建筑搜索 (建筑忽略 involvement)
        $isStructure = false;
        if ($entityType === 'ship') {
            $isStructure = $this->searchService->isStructureTypeId((int) $entityId);
        } elseif ($shipId) {
            $isStructure = $this->searchService->isStructureTypeId((int) $shipId);
        }

        // ====== 策略 1: 舰船/建筑搜索 (Search API types[]) ======
        if ($entityType === 'ship') {
            Log::debug("advancedSearch: 舰船/建筑搜索, type_id={$entityId}, isStructure=" . ($isStructure ? 'Y' : 'N'));

            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced([
                'types' => [(int) $entityId],
            ]);

            if (!empty($kills)) {
                $kills = $this->filterKills($kills, [
                    'system_id' => $systemId,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ]);
                $kills = array_slice($kills, 0, 50);
                [$enriched, $detailsMap] = $this->enrichKillList($kills);
                Log::debug("advancedSearch: 舰船搜索富化后 " . count($enriched) . " 条");
                return $enriched;
            }

            return [];
        }

        // ====== 策略 2: 星系搜索 (Search API systems[]) ======
        if ($entityType === 'system') {
            Log::debug("advancedSearch: 星系搜索, system_id={$entityId}");

            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced([
                'systems' => [(int) $entityId],
            ]);

            if (!empty($kills)) {
                $kills = $this->filterKills($kills, [
                    'ship_id' => $shipId,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ]);
                $kills = array_slice($kills, 0, 50);
                [$enriched, $detailsMap] = $this->enrichKillList($kills);
                Log::debug("advancedSearch: 星系搜索富化后 " . count($enriched) . " 条");
                return $enriched;
            }

            return [];
        }

        // ====== 策略 3: 角色/军团/联盟搜索 ======
        if (!in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            throw new \Exception('不支持的实体类型: ' . $entityType);
        }

        $chartype = '';
        if ($involvement && !$isStructure && isset($chartypeMap[$involvement])) {
            $chartype = $chartypeMap[$involvement];
        }

        $searchParams = [
            'entity_type' => $entityType,
            'entity_id' => (int) $entityId,
        ];
        if (!empty($chartype)) {
            $searchParams['chartype'] = $chartype;
        }
        if ($shipId) {
            $searchParams['types'] = [(int) $shipId];
        }
        if ($systemId) {
            $searchParams['systems'] = [(int) $systemId];
        }
        if ($timeStart) $searchParams['start_date'] = $timeStart;
        if ($timeEnd) $searchParams['end_date'] = $timeEnd;

        $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);

        Log::debug("advancedSearch: Search API 返回 " . count($kills) . " 条");

        if (!empty($kills)) {
            $kills = array_slice($kills, 0, 50);
            [$enriched, $detailsMap] = $this->enrichKillList($kills);
            Log::debug("advancedSearch: 富化后 " . count($enriched) . " 条");
            return $enriched;
        }

        // Search API 无结果 → 回退到 list API
        Log::debug("advancedSearch: Search API 无结果, 回退到 list API");

        $kills = $this->getEntityKills($entityType, (int) $entityId);

        Log::debug("advancedSearch: list API 获取到 " . count($kills) . " 条 KM");

        if (empty($kills)) {
            return [];
        }

        $kills = $this->filterKills($kills, $params);

        if ($involvement && !$isStructure && in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            $kills = $this->preFilterByInvolvement($kills, $entityType, (int) $entityId, $involvement);
            Log::debug("advancedSearch: 预过滤后 " . count($kills) . " 条 KM");
        }

        $kills = array_slice($kills, 0, 50);
        [$enriched, $detailsMap] = $this->enrichKillList($kills);

        if ($involvement && !$isStructure && in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            if (!($involvement === 'victim' && $entityType === 'pilot')) {
                $beforeFilter = count($enriched);
                $enriched = $this->filterByInvolvement($enriched, $entityType, (int) $entityId, $involvement, $detailsMap);
                Log::debug("advancedSearch: ESI involvement 过滤 {$beforeFilter} -> " . count($enriched) . " 条");
            }
        }

        return $enriched;
    }

    /**
     * 按条件过滤 KM 列表
     */
    protected function filterKills(array $kills, array $params): array
    {
        $shipId = $params['ship_id'] ?? null;
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        return array_values(array_filter($kills, function ($kill) use ($shipId, $systemId, $timeStart, $timeEnd) {
            if ($shipId && !empty($kill['ship_type_id']) && (int) $kill['ship_type_id'] !== (int) $shipId) {
                return false;
            }
            if ($systemId && !empty($kill['system_id']) && (int) $kill['system_id'] !== (int) $systemId) {
                return false;
            }
            if ($timeStart && !empty($kill['kill_timestamp'])) {
                $startTs = strtotime($timeStart);
                if ($startTs && $kill['kill_timestamp'] < $startTs) {
                    return false;
                }
            }
            if ($timeEnd && !empty($kill['kill_timestamp'])) {
                if (strpos($timeEnd, 'T') !== false || strpos($timeEnd, ':') !== false) {
                    $endTs = strtotime($timeEnd);
                } else {
                    $endTs = strtotime($timeEnd . ' 23:59:59');
                }
                if ($endTs && $kill['kill_timestamp'] > $endTs) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * 基于 protobuf 列表数据的预过滤
     */
    protected function preFilterByInvolvement(array $kills, string $entityType, int $entityId, string $involvement): array
    {
        if ($involvement === 'victim' && $entityType === 'pilot') {
            $filtered = array_values(array_filter($kills, function ($kill) use ($entityId) {
                return !empty($kill['victim_id']) && (int) $kill['victim_id'] === $entityId;
            }));
            Log::debug("preFilterByInvolvement: pilot+victim 预过滤, 输入 " . count($kills) . " 条, 输出 " . count($filtered) . " 条");
            return $filtered;
        }
        return $kills;
    }

    /**
     * 根据参与类型过滤富化后的 KM 列表
     */
    protected function filterByInvolvement(array $enrichedKills, string $entityType, int $entityId, string $involvement, array $detailsMap = []): array
    {
        $matchCount = 0;
        $noDetailCount = 0;

        $result = array_values(array_filter($enrichedKills, function ($kill) use ($entityType, $entityId, $involvement, $detailsMap, &$matchCount, &$noDetailCount) {
            $killId = $kill['kill_id'];
            $detail = $detailsMap[$killId] ?? Cache::get("kb:kill:{$killId}");
            if (!$detail) {
                try {
                    $detail = $this->getKillDetails($killId);
                } catch (\Exception $e) {
                    $noDetailCount++;
                    return false;
                }
            }

            if (!$detail || empty($detail['victim'])) {
                $noDetailCount++;
                return false;
            }

            $matched = false;
            switch ($involvement) {
                case 'victim':
                    $matched = $this->entityMatchesParticipant($detail['victim'], $entityType, $entityId);
                    break;
                case 'finalblow':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (!empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                case 'attacker':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;
                default:
                    $matched = true;
            }

            if ($matched) $matchCount++;
            return $matched;
        }));

        Log::debug("filterByInvolvement: involvement={$involvement}, entity={$entityType}:{$entityId}, 输入 " . count($enrichedKills) . " 条, 匹配 {$matchCount} 条, 无详情 {$noDetailCount} 条");

        return $result;
    }

    /**
     * 判断某个参与者是否匹配搜索的实体
     */
    protected function entityMatchesParticipant(array $participant, string $entityType, int $entityId): bool
    {
        switch ($entityType) {
            case 'pilot':
                return !empty($participant['character_id']) && (int) $participant['character_id'] === $entityId;
            case 'corporation':
                return !empty($participant['corporation_id']) && (int) $participant['corporation_id'] === $entityId;
            case 'alliance':
                return !empty($participant['alliance_id']) && (int) $participant['alliance_id'] === $entityId;
            default:
                return false;
        }
    }

    // ========================================================
    // KM 列表富化 (批量 ESI 获取详情)
    // ========================================================

    /**
     * 批量富化 KM 列表数据
     */
    public function enrichKillList(array $kills): array
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

        foreach (array_slice($needHash, 0, 20, true) as $idx => $kill) {
            $hash = $this->kbClient->extractEsiHash($kill['kill_id']);
            if ($hash) {
                $kill['esi_hash'] = $hash;
                $toFetch[$idx] = $kill;
                unset($needHash[$idx]);
            }
        }

        foreach ($needHash as $idx => $kill) {
            $enriched[$idx] = $this->buildBasicListItem($kill);
        }

        if (!empty($toFetch)) {
            $batchDetails = $this->batchFetchEsiKills($toFetch);

            foreach ($batchDetails as $idx => $detail) {
                if ($detail) {
                    $enriched[$idx] = $this->extractListDataFromDetail($toFetch[$idx], $detail);
                    $detailsMap[$toFetch[$idx]['kill_id']] = $detail;
                } else {
                    $enriched[$idx] = $this->buildBasicListItem($toFetch[$idx]);
                }
            }

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
     */
    protected function batchFetchEsiKills(array $kills): array
    {
        $results = [];
        $chunks = array_chunk($kills, 20, true);

        foreach ($chunks as $chunk) {
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

            foreach ($responses as $idx => $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                    $esiData = $response->json();
                    if (!empty($esiData) && isset($esiData['victim'])) {
                        $validResponses[(int) $idx] = $esiData;
                        $allIds = array_merge($allIds, $this->collectIds($esiData));
                    }
                }
            }

            $names = $this->resolveNames(array_values(array_unique(array_filter($allIds))));

            foreach ($validResponses as $idx => $esiData) {
                $killId = $kills[$idx]['kill_id'] ?? 0;
                if ($killId > 0) {
                    $detail = $this->buildKillDetailResponse($esiData, $names, $killId);
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
    protected function extractListDataFromDetail(array $kill, array $detail): array
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
    protected function buildBasicListItem(array $kill): array
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
    // 获取 KM 详情
    // ========================================================

    public function getKillDetails(int $killId): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId) {
            $hash = $this->kbClient->extractEsiHash($killId);

            if ($hash === null) {
                return $this->kbClient->getKillDetailsFallback($killId);
            }

            try {
                $esiUrl = $this->esiBaseUrl . "killmails/{$killId}/{$hash}/";
                $response = Http::timeout(15)->get($esiUrl, [
                    'datasource' => $this->datasource,
                ]);

                if (!$response->ok()) {
                    Log::warning("ESI killmail 请求失败: HTTP {$response->status()} for kill {$killId}");
                    return $this->kbClient->getKillDetailsFallback($killId);
                }

                $esiData = $response->json();
                if (empty($esiData) || !isset($esiData['victim'])) {
                    return $this->kbClient->getKillDetailsFallback($killId);
                }

                $idsToResolve = $this->collectIds($esiData);
                $names = $this->resolveNames($idsToResolve);

                return $this->buildKillDetailResponse($esiData, $names, $killId);
            } catch (\Exception $e) {
                Log::warning("ESI killmail 处理异常: " . $e->getMessage());
                return $this->kbClient->getKillDetailsFallback($killId);
            }
        });
    }

    public function getKillDetailsByHash(int $killId, string $hash): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId, $hash) {
            Cache::put("kb:esi_hash:{$killId}", $hash, 86400);

            $esiUrl = $this->esiBaseUrl . "killmails/{$killId}/{$hash}/";
            $response = Http::timeout(15)->get($esiUrl, [
                'datasource' => $this->datasource,
            ]);

            if (!$response->ok()) {
                throw new \Exception("ESI 请求失败: HTTP {$response->status()}");
            }

            $esiData = $response->json();
            if (empty($esiData) || !isset($esiData['victim'])) {
                throw new \Exception('ESI 返回数据无效');
            }

            $idsToResolve = $this->collectIds($esiData);
            $names = $this->resolveNames($idsToResolve);

            return $this->buildKillDetailResponse($esiData, $names, $killId);
        });
    }

    // ========================================================
    // 星系信息查询
    // ========================================================

    public function getSystemInfo(int $systemId): array
    {
        $cacheKey = "eve_system_info:{$systemId}";

        return Cache::remember($cacheKey, 86400, function () use ($systemId) {
            $info = ['security_status' => null, 'region_name' => null, 'constellation_name' => null];

            // 1. 优先从本地数据获取
            $localSystem = EveDataService::getLocalSystemInfo($systemId);
            if ($localSystem) {
                $info['security_status'] = isset($localSystem['security']) ? round($localSystem['security'], 1) : null;

                // 获取星座名称
                if (!empty($localSystem['constellation_id'])) {
                    $localConst = EveDataService::getLocalConstellationInfo((int)$localSystem['constellation_id']);
                    $info['constellation_name'] = $localConst['name'] ?? null;
                }

                // 获取星域名称
                if (!empty($localSystem['region_id'])) {
                    $regionName = EveDataService::getLocalRegionName($localSystem['region_id']);
                    if ($regionName) {
                        $info['region_name'] = $regionName;
                        return $info;
                    }
                }
            }

            // 2. 本地没有完整数据，调用 ESI API 兜底
            try {
                $sysResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/systems/{$systemId}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if (!$sysResponse->ok()) return $info;

                $sysData = $sysResponse->json();
                $info['security_status'] = round($sysData['security_status'] ?? 0, 1);

                $constellationId = $sysData['constellation_id'] ?? null;
                if (!$constellationId) return $info;

                // 尝试从本地获取星座和星域信息
                $localConst = EveDataService::getLocalConstellationInfo($constellationId);
                if ($localConst && !empty($localConst['region_id'])) {
                    $info['constellation_name'] = $localConst['name'] ?? null;
                    $regionName = EveDataService::getLocalRegionName($localConst['region_id']);
                    if ($regionName) {
                        $info['region_name'] = $regionName;
                        return $info;
                    }
                }

                // 本地没有星座数据，继续 API 调用
                $constResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/constellations/{$constellationId}/",
                    ['datasource' => $this->datasource]
                );

                if (!$constResponse->ok()) return $info;

                $constData = $constResponse->json();
                $regionId = $constData['region_id'] ?? null;
                if (!$regionId) return $info;

                // 尝试从本地获取星域名称
                $regionName = EveDataService::getLocalRegionName($regionId);
                if ($regionName) {
                    $info['region_name'] = $regionName;
                    return $info;
                }

                // 本地没有星域数据，调用 API
                $regionResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/regions/{$regionId}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if ($regionResponse->ok()) {
                    $regionData = $regionResponse->json();
                    $info['region_name'] = $regionData['name'] ?? null;
                }
            } catch (\Exception $e) {
                Log::debug("获取星系信息失败 ({$systemId}): " . $e->getMessage());
            }

            return $info;
        });
    }

    // ========================================================
    // 市场价格查询
    // ========================================================

    protected function getMarketPrices(): array
    {
        return Cache::remember('esi_market_prices', 86400, function () {
            try {
                $response = Http::timeout(30)->get(
                    $this->esiBaseUrl . 'markets/prices/',
                    ['datasource' => $this->datasource]
                );

                if (!$response->ok()) return [];

                $prices = [];
                foreach ($response->json() as $item) {
                    $typeId = $item['type_id'] ?? 0;
                    $price = $item['average_price'] ?? $item['adjusted_price'] ?? 0;
                    if ($typeId > 0 && $price > 0) {
                        $prices[$typeId] = round($price, 2);
                    }
                }
                return $prices;
            } catch (\Exception $e) {
                Log::debug('获取市场价格失败: ' . $e->getMessage());
                return [];
            }
        });
    }

    // ========================================================
    // 名称解析
    // ========================================================

    protected function collectIds(array $esiData): array
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

        return array_values(array_unique(array_filter($ids)));
    }

    protected function resolveNames(array $ids): array
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

        // 4. 调用 ESI API 查询剩余的 (主要是角色、军团、联盟等动态数据)
        if (!empty($missing)) {
            $this->resolveNamesFromEsi($missing, $names);
        }

        return $names;
    }

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
    // 物品槽位映射
    // ========================================================

    protected function mapItemFlag(int $flag): string
    {
        if ($flag >= 27 && $flag <= 34) return '高槽 ' . ($flag - 26);
        if ($flag >= 19 && $flag <= 26) return '中槽 ' . ($flag - 18);
        if ($flag >= 11 && $flag <= 18) return '低槽 ' . ($flag - 10);
        if ($flag >= 92 && $flag <= 94) return '改装件 ' . ($flag - 91);
        if ($flag >= 125 && $flag <= 132) return '子系统 ' . ($flag - 124);

        $map = [
            5 => '货柜舱',
            87 => '无人机舱',
            88 => '无人机舱',
            89 => '弹药舱',
            90 => '弹药舱',
        ];

        return $map[$flag] ?? '';
    }

    protected function mapSlotGroup(int $flag): string
    {
        if ($flag >= 27 && $flag <= 34) return '高槽';
        if ($flag >= 19 && $flag <= 26) return '中槽';
        if ($flag >= 11 && $flag <= 18) return '低槽';
        if ($flag >= 92 && $flag <= 94) return '改装件';
        if ($flag >= 125 && $flag <= 132) return '子系统';
        if ($flag >= 87 && $flag <= 88) return '无人机舱';
        if ($flag === 5) return '货柜舱';
        if ($flag >= 89 && $flag <= 90) return '弹药舱';
        return '其他';
    }

    // ========================================================
    // 组装 KM 详情返回数据
    // ========================================================

    protected function buildKillDetailResponse(array $esiData, array $names, int $killId): array
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

        $marketPrices = $this->getMarketPrices();

        // 处理受害者物品
        $items = [];
        $itemsBySlot = [];
        $slotOrder = ['高槽', '中槽', '低槽', '改装件', '子系统', '无人机舱', '弹药舱', '货柜舱', '其他'];

        foreach ($victim['items'] ?? [] as $item) {
            $typeId = $item['item_type_id'] ?? 0;
            $flag = $item['flag'] ?? 0;
            $slotGroup = $this->mapSlotGroup($flag);
            $unitPrice = $marketPrices[$typeId] ?? 0;

            if (!empty($item['quantity_destroyed']) && !empty($item['quantity_dropped'])) {
                $destroyedQty = (int) $item['quantity_destroyed'];
                $droppedQty = (int) $item['quantity_dropped'];
                $destroyedItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $destroyedQty,
                    'status' => 'destroyed',
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $destroyedQty, 2),
                ];
                $droppedItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $droppedQty,
                    'status' => 'dropped',
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $droppedQty, 2),
                ];
                $items[] = $destroyedItem;
                $items[] = $droppedItem;
                $itemsBySlot[$slotGroup][] = $destroyedItem;
                $itemsBySlot[$slotGroup][] = $droppedItem;
            } else {
                $qty = ($item['quantity_destroyed'] ?? 0) + ($item['quantity_dropped'] ?? 0);
                $status = isset($item['quantity_dropped']) && $item['quantity_dropped'] > 0 ? 'dropped' : 'destroyed';
                $qty = $qty > 0 ? $qty : 1;
                $singleItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $qty,
                    'status' => $status,
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $qty, 2),
                ];
                $items[] = $singleItem;
                $itemsBySlot[$slotGroup][] = $singleItem;
            }
        }

        $orderedItemsBySlot = [];
        foreach ($slotOrder as $slot) {
            if (!empty($itemsBySlot[$slot])) {
                $orderedItemsBySlot[$slot] = $itemsBySlot[$slot];
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

        usort($attackers, function ($a, $b) {
            if ($a['final_blow'] && !$b['final_blow']) return -1;
            if (!$a['final_blow'] && $b['final_blow']) return 1;
            return $b['damage_done'] <=> $a['damage_done'];
        });

        $solarSystemId = $esiData['solar_system_id'] ?? null;
        $systemSec = null;
        $regionName = null;
        $constellationName = null;
        if ($solarSystemId) {
            $sysInfo = $this->getSystemInfo((int) $solarSystemId);
            $systemSec = $sysInfo['security_status'];
            $regionName = $sysInfo['region_name'];
            $constellationName = $sysInfo['constellation_name'] ?? null;
        }

        return [
            'kill_id' => $killId,
            'kill_time' => $killTimeBj,
            'kill_time_raw' => $killTimeRaw,
            'solar_system_id' => $solarSystemId,
            'solar_system_name' => $solarSystemId ? ($names[$solarSystemId] ?? "未知#{$solarSystemId}") : null,
            'system_sec' => $systemSec,
            'constellation_name' => $constellationName,
            'region_name' => $regionName,
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
                'items' => $items,
            ],
            'items_by_slot' => $orderedItemsBySlot,
            'attackers' => $attackers,
            'attacker_count' => count($attackers),
        ];
    }
}
