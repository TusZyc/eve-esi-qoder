<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;
use App\Services\Killmail\ProtobufCodec;
use App\Services\Killmail\BetaKbApiClient;
use App\Services\Killmail\KillmailSearchService;
use App\Services\Killmail\KillmailEnrichService;
use App\Services\Killmail\KillmailFilterService;

/**
 * Killmail 服务 - 门面模式
 * 
 * 委托子服务处理具体功能：
 * - ProtobufCodec: Protobuf 编解码
 * - BetaKbApiClient: Beta KB API 交互
 * - KillmailSearchService: 搜索功能
 * - KillmailEnrichService: KM 数据富化
 * - KillmailFilterService: KM 数据过滤
 * 
 * 本类保留高级搜索编排、详情获取等核心业务逻辑
 * 
 * [重构优化] 2026-04-06: 提取富化和过滤逻辑到独立服务
 */
class KillmailService
{
    // 缓存时间常量（秒）
    public const CACHE_TTL_ENTITY_KILLS = 600;      // 实体KM列表：10分钟
    public const CACHE_TTL_KILL_DETAIL = 3600;      // KM详情：1小时
    public const CACHE_TTL_ESI_HASH = 86400;        // ESI Hash：24小时
    public const CACHE_TTL_SYSTEM_INFO = 86400;     // 星系信息：24小时
    public const CACHE_TTL_MARKET_PRICES = 86400;   // 市场价格：24小时

    protected string $esiBaseUrl;
    protected string $datasource;
    protected EveDataService $eveData;
    protected ProtobufCodec $codec;
    protected BetaKbApiClient $kbClient;
    protected KillmailSearchService $searchService;
    protected KillmailEnrichService $enrichService;
    protected KillmailFilterService $filterService;

    public function __construct(
        EveDataService $eveData,
        ProtobufCodec $codec,
        BetaKbApiClient $kbClient,
        KillmailSearchService $searchService,
        KillmailEnrichService $enrichService,
        KillmailFilterService $filterService
    ) {
        $this->eveData = $eveData;
        $this->codec = $codec;
        $this->kbClient = $kbClient;
        $this->searchService = $searchService;
        $this->enrichService = $enrichService;
        $this->filterService = $filterService;
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

        return Cache::remember($cacheKey, self::CACHE_TTL_ENTITY_KILLS, function () use ($entityType, $entityId) {
            try {
                $kills = $this->kbClient->fetchBetaEntityKillList($entityType, $entityId);
                if (!empty($kills)) {
                    return $kills;
                }
            } catch (\Exception $e) {
                Log::warning("Beta KB entity list failed ({$entityType} {$entityId}): " . $e->getMessage());
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
        $shipEntityType = $params['ship_entity_type'] ?? null; // 舰船类型: ship 或 ship_group
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        // 处理舰船分组过滤：如果是 ship_group，先解析为具体舰船类型ID列表
        $shipTypeIds = null;
        if ($shipId) {
            if ($shipEntityType === 'ship_group') {
                $shipTypeIds = $this->searchService->getShipGroupTypeIds((int) $shipId);
                Log::debug("advancedSearch: 舰船分组 {$shipId} 解析为 " . count($shipTypeIds) . " 种舰船类型");
            } else {
                $shipTypeIds = [(int) $shipId];
            }
        }

        if (!$entityType || !$entityId) {
            // 仅时间搜索: 直接用 Search API 的日期范围
            if ($timeStart || $timeEnd) {
                $searchParams = [];
                if ($shipTypeIds) $searchParams['types'] = $shipTypeIds;
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
        // [优化] 支持舰船+星系组合搜索，将所有条件传递给API在服务端过滤
        if ($entityType === 'ship') {
            Log::debug("advancedSearch: 舰船/建筑搜索, type_id={$entityId}, system_id={$systemId}, isStructure=" . ($isStructure ? 'Y' : 'N'));

            // 构建搜索参数，支持types + systems + 时间范围组合
            $searchParams = [
                'types' => [(int) $entityId],
            ];
            if ($systemId) {
                $searchParams['systems'] = [(int) $systemId];
            }
            if ($timeStart) $searchParams['start_date'] = $timeStart;
            if ($timeEnd) $searchParams['end_date'] = $timeEnd;

            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);

            if (!empty($kills)) {
                // API已过滤主要条件，这里只做时间过滤（如果API未处理）
                $kills = $this->filterKills($kills, [
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

        // ====== 策略 1.5: 舰船类别搜索 (ship_group) ======
        // [新增] 支持按舰船类别（如"货舰"、"战列舰"）搜索
        if ($entityType === 'ship_group') {
            Log::debug("advancedSearch: 舰船类别搜索, group_id={$entityId}, system_id={$systemId}");

            // 获取该分组下的所有舰船 type_id
            $groupIds = $this->searchService->getShipGroupTypeIds((int) $entityId);
            
            if (empty($groupIds)) {
                Log::warning("advancedSearch: 舰船类别 {$entityId} 下无舰船类型");
                return [];
            }

            Log::debug("advancedSearch: 舰船类别 {$entityId} 包含 " . count($groupIds) . " 种舰船");

            // 构建搜索参数，批量搜索该类别下的所有舰船
            $searchParams = [
                'types' => $groupIds,
            ];
            if ($systemId) {
                $searchParams['systems'] = [(int) $systemId];
            }
            if ($timeStart) $searchParams['start_date'] = $timeStart;
            if ($timeEnd) $searchParams['end_date'] = $timeEnd;

            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);

            if (!empty($kills)) {
                $kills = $this->filterKills($kills, [
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ]);
                $kills = array_slice($kills, 0, 50);
                [$enriched, $detailsMap] = $this->enrichKillList($kills);
                Log::debug("advancedSearch: 舰船类别搜索富化后 " . count($enriched) . " 条");
                return $enriched;
            }

            return [];
        }

        // ====== 策略 2: 星系搜索 (Search API systems[]) ======
        // [优化] 支持星系+舰船组合搜索，将所有条件传递给API在服务端过滤
        if ($entityType === 'system') {
            Log::debug("advancedSearch: 星系搜索, system_id={$entityId}, ship_id={$shipId}");

            // 构建搜索参数，支持systems + types + 时间范围组合
            $searchParams = [
                'systems' => [(int) $entityId],
            ];
            if ($shipTypeIds) {
                $searchParams['types'] = $shipTypeIds;
            }
            if ($timeStart) $searchParams['start_date'] = $timeStart;
            if ($timeEnd) $searchParams['end_date'] = $timeEnd;

            $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);

            if (!empty($kills)) {
                // API已过滤主要条件，这里只做时间过滤（如果API未处理）
                $kills = $this->filterKills($kills, [
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

        // [优化] Beta KB API 支持 entity + types 的组合搜索
        // 直接将舰船类型列表传递给API进行服务端过滤，提高查询效率和准确性
        $searchParams = [
            'entity_type' => $entityType,
            'entity_id' => (int) $entityId,
        ];
        if (!empty($chartype)) {
            $searchParams['chartype'] = $chartype;
        }
        // 直接传递舰船类型列表给API（支持单个或多个）
        if ($shipTypeIds) {
            $searchParams['types'] = $shipTypeIds;
        }
        if ($systemId) {
            $searchParams['systems'] = [(int) $systemId];
        }
        if ($timeStart) $searchParams['start_date'] = $timeStart;
        if ($timeEnd) $searchParams['end_date'] = $timeEnd;

        $kills = $this->kbClient->fetchBetaSearchKillsAdvanced($searchParams);

        Log::debug("advancedSearch: Search API 返回 " . count($kills) . " 条");

        if (!empty($kills)) {
            // API已做服务端过滤，直接截断
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
     * [重构优化] 委托给 KillmailFilterService
     */
    protected function filterKills(array $kills, array $params): array
    {
        return $this->filterService->filterKills($kills, $params);
    }

    /**
     * 基于 protobuf 列表数据的预过滤
     * [重构优化] 委托给 KillmailFilterService
     */
    protected function preFilterByInvolvement(array $kills, string $entityType, int $entityId, string $involvement): array
    {
        return $this->filterService->preFilterByInvolvement($kills, $entityType, $entityId, $involvement);
    }

    /**
     * 根据参与类型过滤富化后的 KM 列表
     * [重构优化] 委托给 KillmailFilterService
     */
    protected function filterByInvolvement(array $enrichedKills, string $entityType, int $entityId, string $involvement, array $detailsMap = []): array
    {
        return $this->filterService->filterByInvolvement(
            $enrichedKills,
            $entityType,
            $entityId,
            $involvement,
            $detailsMap,
            function ($killId) {
                return $this->getKillDetails($killId);
            }
        );
    }

    // ========================================================
    // KM 列表富化 - 委托给 KillmailEnrichService
    // ========================================================

    /**
     * 批量富化 KM 列表数据
     * [重构优化] 委托给 KillmailEnrichService
     */
    public function enrichKillList(array $kills): array
    {
        // 传入构建详情响应的回调函数，以便使用本类的 buildKillDetailResponse 方法
        return $this->enrichService->enrichKillList($kills, function ($esiData, $names, $killId) {
            return $this->buildKillDetailResponse($esiData, $names, $killId);
        });
    }

    // ========================================================
    // 辅助方法 - 委托给 KillmailEnrichService
    // ========================================================

    /**
     * 收集 ESI 数据中需要解析名称的 ID
     * [重构优化] 委托给 KillmailEnrichService
     */
    protected function collectIds(array $esiData): array
    {
        return $this->enrichService->collectIds($esiData);
    }

    /**
     * 批量解析 ID 到名称
     * [重构优化] 委托给 KillmailEnrichService
     */
    protected function resolveNames(array $ids): array
    {
        return $this->enrichService->resolveNames($ids);
    }

    // ========================================================
    // 获取 KM 详情
    // ========================================================

    public function getKillDetails(int $killId): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_KILL_DETAIL, function () use ($killId) {
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

        return Cache::remember($cacheKey, self::CACHE_TTL_KILL_DETAIL, function () use ($killId, $hash) {
            Cache::put("kb:esi_hash:{$killId}", $hash, self::CACHE_TTL_ESI_HASH);

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

        return Cache::remember($cacheKey, self::CACHE_TTL_SYSTEM_INFO, function () use ($systemId) {
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
        return Cache::remember('esi_market_prices', self::CACHE_TTL_MARKET_PRICES, function () {
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

        // 处理支援者（独立于 attackers）
        $supporters = [];
        foreach ($esiData['supporters'] ?? [] as $sup) {
            $supporters[] = [
                'character_id'    => $sup['character_id'] ?? null,
                'character_name'  => isset($sup['character_id']) ? ($names[$sup['character_id']] ?? "未知#{$sup['character_id']}") : null,
                'corporation_id'  => $sup['corporation_id'] ?? null,
                'corporation_name'=> isset($sup['corporation_id']) ? ($names[$sup['corporation_id']] ?? null) : null,
                'alliance_id'     => $sup['alliance_id'] ?? null,
                'alliance_name'   => isset($sup['alliance_id']) ? ($names[$sup['alliance_id']] ?? null) : null,
                'ship_type_id'    => $sup['ship_type_id'] ?? null,
                'ship_name'       => isset($sup['ship_type_id']) ? ($names[$sup['ship_type_id']] ?? null) : null,
                'repairer_type_id'=> $sup['repairer_type_id'] ?? null,
                'repairer_name'   => isset($sup['repairer_type_id']) ? ($names[$sup['repairer_type_id']] ?? null) : null,
                'repair_done'     => (int)($sup['repair_done'] ?? 0),
                'is_regen'        => (bool)($sup['is_regen'] ?? false),
            ];
        }

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
                'position' => $victim['position'] ?? null,
                'items' => $items,
            ],
            'items_by_slot' => $orderedItemsBySlot,
            'attackers' => $attackers,
            'attacker_count' => count($attackers),
            'supporters' => $supporters,
            'supporter_count' => count($supporters),
        ];
    }
}
