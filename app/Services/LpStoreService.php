<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class LpStoreService
{
    private EveDataService $eveDataService;
    private MarketService $marketService;
    private string $baseUrl;
    private string $datasource = 'serenity';

    public function __construct(EveDataService $eveDataService, MarketService $marketService)
    {
        $this->eveDataService = $eveDataService;
        $this->marketService = $marketService;
        $this->baseUrl = rtrim(config('lpstore.esi_base_url', 'https://ali-esi.evepc.163.com/latest'), '/');
    }

    /**
     * 获取势力和公司数据
     */
    public function getFactions(): array
    {
        $ttl = config('lpstore.cache_ttl.factions', 86400);
        
        return Cache::remember('lp_factions_translated', $ttl, function () {
            // 优先读取 lp_factions.json
            $path = base_path('data/lp_factions.json');
            if (!file_exists($path)) {
                // 兜底使用 faction_corps_map.json
                $path = base_path('data/faction_corps_map.json');
            }
            
            if (!file_exists($path)) {
                Log::warning('势力数据文件不存在');
                return [];
            }

            $content = file_get_contents($path);
            $data = json_decode($content, true);
            
            if (!is_array($data)) {
                return [];
            }

            // 处理 faction_corps_map.json 格式（有 factions 包装）
            $factions = $data['factions'] ?? $data;
            if (!is_array($factions)) {
                return [];
            }

            // 收集所有公司 ID（兼容 corporation_id 和 id 两种字段名）
            $corpIds = [];
            foreach ($factions as $faction) {
                if (!empty($faction['corporations'])) {
                    foreach ($faction['corporations'] as $corp) {
                        $corpId = $corp['corporation_id'] ?? $corp['id'] ?? null;
                        if ($corpId !== null) {
                            $corpIds[] = (int)$corpId;
                        }
                    }
                }
            }

            // 批量翻译公司名称
            $corpNames = [];
            if (!empty($corpIds)) {
                $corpNames = $this->eveDataService->getNamesByIds(array_unique($corpIds));
            }

            // 替换公司名称为中文，并规范化字段名
            foreach ($factions as &$faction) {
                if (!empty($faction['corporations'])) {
                    $normalizedCorps = [];
                    foreach ($faction['corporations'] as $corp) {
                        $corpId = (int)($corp['corporation_id'] ?? $corp['id'] ?? 0);
                        if ($corpId > 0) {
                            $normalizedCorps[] = [
                                'corporation_id' => $corpId,
                                'id' => $corpId, // 兼容性保留
                                'name' => $corpNames[$corpId] ?? ($corp['name'] ?? '公司#' . $corpId),
                            ];
                        }
                    }
                    $faction['corporations'] = $normalizedCorps;
                }
            }

            // 过滤掉无效的势力（名称为空或为"未知"的条目）
            $factions = array_filter($factions, function ($faction) {
                $name = $faction['faction_name'] ?? $faction['name'] ?? '';
                return !empty($name) && $name !== '未知' && $name !== 'Unknown' && !str_starts_with($name, '未知');
            });

            return array_values($factions);
        });
    }

    /**
     * 获取公司的 LP 商店报价
     */
    public function getOffers(int $corporationId): array
    {
        $ttl = config('lpstore.cache_ttl.offers', 3600);
        $cacheKey = "lp_offers_{$corporationId}";

        return Cache::remember($cacheKey, $ttl, function () use ($corporationId) {
            try {
                $response = Http::timeout(30)->get($this->baseUrl . '/loyalty/stores/' . $corporationId . '/offers/', [
                    'datasource' => $this->datasource,
                ]);

                if ($response->ok()) {
                    return $response->json() ?: [];
                }

                Log::warning("获取 LP 商店报价失败 (corp={$corporationId}): HTTP " . $response->status());
                return [];
            } catch (\Exception $e) {
                Log::error("获取 LP 商店报价异常 (corp={$corporationId}): " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * 获取全局市场价格
     */
    public function getAllMarketPrices(): array
    {
        $ttl = config('lpstore.cache_ttl.prices', 3600);
        
        return Cache::remember('lp_market_prices', $ttl, function () {
            try {
                $response = Http::timeout(30)->get($this->baseUrl . '/markets/prices/', [
                    'datasource' => $this->datasource,
                ]);

                if (!$response->ok()) {
                    Log::warning('获取市场价格失败: HTTP ' . $response->status());
                    return [];
                }

                $prices = $response->json() ?: [];
                $priceMap = [];

                foreach ($prices as $item) {
                    $typeId = $item['type_id'] ?? null;
                    if ($typeId) {
                        // 优先使用 average_price，没有则用 adjusted_price
                        $priceMap[$typeId] = [
                            'average_price' => $item['average_price'] ?? null,
                            'adjusted_price' => $item['adjusted_price'] ?? null,
                        ];
                    }
                }

                return $priceMap;
            } catch (\Exception $e) {
                Log::error('获取市场价格异常: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * 计算 LP 报价的利润
     */
    public function calculateOfferProfits(array $offers, array $prices): array
    {
        $result = [];
        $typeIds = [];

        // 收集所有需要解析名称的物品 ID
        foreach ($offers as $offer) {
            $typeIds[] = $offer['type_id'];
            if (!empty($offer['required_items'])) {
                foreach ($offer['required_items'] as $item) {
                    $typeIds[] = $item['type_id'];
                }
            }
        }

        // 批量获取物品名称
        $typeIds = array_unique($typeIds);
        $names = $this->eveDataService->getNamesByIds($typeIds);

        foreach ($offers as $offer) {
            $typeId = $offer['type_id'];
            $lpCost = $offer['lp_cost'] ?? 0;
            $iskCost = $offer['isk_cost'] ?? 0;
            $quantity = $offer['quantity'] ?? 1;

            // 计算产出物品价格
            $priceInfo = $prices[$typeId] ?? null;
            $unitPrice = 0;
            if ($priceInfo) {
                $unitPrice = $priceInfo['average_price'] ?? $priceInfo['adjusted_price'] ?? 0;
            }
            $revenue = $unitPrice * $quantity;

            // 计算材料成本
            $materialCost = 0;
            $materialsDetail = [];
            if (!empty($offer['required_items'])) {
                foreach ($offer['required_items'] as $reqItem) {
                    $matTypeId = $reqItem['type_id'];
                    $matQuantity = $reqItem['quantity'] ?? 1;
                    
                    $matPriceInfo = $prices[$matTypeId] ?? null;
                    $matUnitPrice = 0;
                    if ($matPriceInfo) {
                        $matUnitPrice = $matPriceInfo['average_price'] ?? $matPriceInfo['adjusted_price'] ?? 0;
                    }
                    $matTotalCost = $matUnitPrice * $matQuantity;
                    $materialCost += $matTotalCost;

                    $materialsDetail[] = [
                        'type_id' => $matTypeId,
                        'name' => $names[$matTypeId] ?? ('物品#' . $matTypeId),
                        'quantity' => $matQuantity,
                        'unit_price' => $matUnitPrice,
                        'total_cost' => $matTotalCost,
                    ];
                }
            }

            // 计算利润和 LP 比例
            $profit = $revenue - $iskCost - $materialCost;
            $lpRatio = $lpCost > 0 ? round($profit / $lpCost, 2) : 0;

            $result[] = [
                'offer_id' => $offer['offer_id'] ?? null,
                'type_id' => $typeId,
                'item_name' => $names[$typeId] ?? ('物品#' . $typeId),
                'quantity' => $quantity,
                'lp_cost' => $lpCost,
                'isk_cost' => $iskCost,
                'ak_cost' => $offer['ak_cost'] ?? null,
                'required_items' => $offer['required_items'] ?? [],
                'unit_price' => $unitPrice,
                'revenue' => round($revenue, 2),
                'material_cost' => round($materialCost, 2),
                'profit' => round($profit, 2),
                'lp_ratio' => $lpRatio,
                'materials_detail' => $materialsDetail,
            ];
        }

        return $result;
    }

    /**
     * 批量获取物品的市场历史数据
     */
    public function getItemsHistory(int $regionId, array $typeIds): array
    {
        $ttl = config('lpstore.cache_ttl.history', 21600);
        $concurrency = config('lpstore.history_concurrent_requests', 15);

        // 检查缓存并收集未缓存的 ID
        $result = [];
        $uncachedIds = [];
        foreach ($typeIds as $typeId) {
            $cacheKey = "lp_history_{$regionId}_{$typeId}";
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $result[$typeId] = $cached;
            } else {
                $uncachedIds[] = $typeId;
            }
        }

        if (empty($uncachedIds)) {
            return $result;
        }

        // 并发请求未缓存的历史数据
        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
        ]);

        $requests = function () use ($uncachedIds, $regionId) {
            foreach ($uncachedIds as $typeId) {
                $url = $this->baseUrl . '/markets/' . $regionId . '/history/?datasource=' . $this->datasource . '&type_id=' . $typeId;
                yield $typeId => new Request('GET', $url);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $typeId) use (&$result, $regionId, $ttl) {
                try {
                    $data = json_decode($response->getBody()->getContents(), true);
                    $historyData = $this->processHistoryData($data ?: []);
                    $result[$typeId] = $historyData;
                    
                    // 缓存结果
                    $cacheKey = "lp_history_{$regionId}_{$typeId}";
                    Cache::put($cacheKey, $historyData, $ttl);
                } catch (\Exception $e) {
                    Log::warning("处理历史数据失败 (type={$typeId}): " . $e->getMessage());
                    $result[$typeId] = ['volume_7d' => 0, 'turnover_7d' => 0];
                }
            },
            'rejected' => function ($reason, $typeId) use (&$result, $regionId, $ttl) {
                Log::warning("获取历史数据失败 (type={$typeId}): " . $reason->getMessage());
                $defaultData = ['volume_7d' => 0, 'turnover_7d' => 0];
                $result[$typeId] = $defaultData;
                
                // 缓存空结果避免重复请求
                $cacheKey = "lp_history_{$regionId}_{$typeId}";
                Cache::put($cacheKey, $defaultData, 300); // 失败时短缓存
            },
        ]);

        $pool->promise()->wait();

        return $result;
    }

    /**
     * 处理历史数据，计算 7 天统计
     */
    private function processHistoryData(array $history): array
    {
        if (empty($history)) {
            return ['volume_7d' => 0, 'turnover_7d' => 0];
        }

        // 按日期降序排序
        usort($history, function ($a, $b) {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });

        // 取最近 7 天的数据
        $recentDays = array_slice($history, 0, 7);
        
        $volume7d = 0;
        $turnover7d = 0;

        foreach ($recentDays as $day) {
            $volume = $day['volume'] ?? 0;
            $average = $day['average'] ?? 0;
            $volume7d += $volume;
            $turnover7d += $volume * $average;
        }

        return [
            'volume_7d' => $volume7d,
            'turnover_7d' => round($turnover7d, 2),
        ];
    }

    /**
     * 获取指定物品的市场订单
     */
    public function getOrders(int $regionId, int $typeId): array
    {
        $ttl = config('lpstore.cache_ttl.orders', 300);
        $cacheKey = "lp_orders_{$regionId}_{$typeId}";

        return Cache::remember($cacheKey, $ttl, function () use ($regionId, $typeId) {
            try {
                $response = Http::timeout(15)->get($this->baseUrl . '/markets/' . $regionId . '/orders/', [
                    'datasource' => $this->datasource,
                    'type_id' => $typeId,
                    'order_type' => 'all',
                ]);

                if (!$response->ok()) {
                    Log::warning("获取订单失败 (region={$regionId}, type={$typeId}): HTTP " . $response->status());
                    return ['sell_orders' => [], 'buy_orders' => []];
                }

                $orders = $response->json() ?: [];
                
                // 使用 MarketService 的翻译机制获取中文空间站名
                $orders = $this->marketService->enrichOrdersWithLocation($orders);
                
                $sellOrders = [];
                $buyOrders = [];

                foreach ($orders as $order) {
                    $orderData = $this->formatOrder($order);
                    
                    if ($order['is_buy_order'] ?? false) {
                        $buyOrders[] = $orderData;
                    } else {
                        $sellOrders[] = $orderData;
                    }
                }

                // 卖单按价格升序
                usort($sellOrders, function ($a, $b) {
                    return $a['price'] <=> $b['price'];
                });

                // 买单按价格降序
                usort($buyOrders, function ($a, $b) {
                    return $b['price'] <=> $a['price'];
                });

                return [
                    'sell_orders' => $sellOrders,
                    'buy_orders' => $buyOrders,
                ];
            } catch (\Exception $e) {
                Log::error("获取订单异常 (region={$regionId}, type={$typeId}): " . $e->getMessage());
                return ['sell_orders' => [], 'buy_orders' => []];
            }
        });
    }

    /**
     * 批量解析位置名称（空间站/建筑）
     */
    private function batchResolveLocationNames(array $locationIds): array
    {
        $result = [];
        $stationIds = [];
        $structureIds = [];
        
        foreach ($locationIds as $id) {
            if ($id >= 1000000000000) {
                // 玩家建筑 ID
                $structureIds[] = $id;
                $result[$id] = '玩家建筑';
            } elseif ($id > 0) {
                // NPC 空间站
                $stationIds[] = $id;
            }
        }
        
        // 使用 /universe/names/ 批量获取中文名称
        if (!empty($stationIds)) {
            $names = $this->eveDataService->getNamesByIds($stationIds);
            foreach ($names as $id => $name) {
                $result[$id] = $name;
            }
        }
        
        return $result;
    }

    /**
     * 格式化订单数据
     * @param array $order 原始订单数据（已经通过 enrichOrdersWithLocation 添加了 location_name）
     */
    private function formatOrder(array $order): array
    {
        $locationId = $order['location_id'] ?? 0;
        
        // 优先使用 enrichOrdersWithLocation 添加的 location_name
        $locationName = $order['location_name'] ?? $this->resolveLocationName($locationId);

        // 计算过期时间
        $expires = null;
        if (isset($order['issued']) && isset($order['duration'])) {
            try {
                $issued = new \DateTime($order['issued']);
                $issued->modify('+' . (int)$order['duration'] . ' days');
                $expires = $issued->format('Y-m-d\TH:i:s\Z');
            } catch (\Exception $e) {
                // ignore
            }
        }

        return [
            'order_id' => $order['order_id'] ?? null,
            'price' => $order['price'] ?? 0,
            'volume_remain' => $order['volume_remain'] ?? 0,
            'volume_total' => $order['volume_total'] ?? 0,
            'location_id' => $locationId,
            'location_name' => $locationName,
            'issued' => $order['issued'] ?? null,
            'expires_at' => $expires,
            'min_volume' => $order['min_volume'] ?? 1,
            'range' => $order['range'] ?? 'station',
        ];
    }

    /**
     * 解析位置名称
     */
    private function resolveLocationName(int $locationId): string
    {
        if ($locationId <= 0) {
            return '未知位置';
        }

        // 尝试从 EveDataService 获取
        $name = $this->eveDataService->getNameById($locationId);
        if ($name && !str_starts_with($name, '未知')) {
            return $name;
        }

        // 玩家建筑
        if ($locationId >= 1000000000000) {
            return '玩家建筑';
        }

        return "位置#{$locationId}";
    }
}
