<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LpStoreService;
use App\Services\EveDataService;
use App\Services\MarketService;

class LpStoreController extends Controller
{
    private LpStoreService $lpStoreService;
    private EveDataService $eveDataService;
    private MarketService $marketService;

    public function __construct(LpStoreService $lpStoreService, EveDataService $eveDataService, MarketService $marketService)
    {
        $this->lpStoreService = $lpStoreService;
        $this->eveDataService = $eveDataService;
        $this->marketService = $marketService;
    }

    /**
     * 显示 LP 商店页面
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isLoggedIn = $user !== null;

        // 从缓存获取所有 K-space 星域（有市场功能的星域）
        $cachedRegions = $this->marketService->getAllRegions();
        $marketRegions = [];
        
        if (!empty($cachedRegions)) {
            foreach ($cachedRegions as $region) {
                $id = $region['id'] ?? $region['region_id'] ?? null;
                $name = $region['name'] ?? '';
                if ($id && $name) {
                    $marketRegions[$id] = $name;
                }
            }
        } else {
            // 备选方案：使用配置中的硬编码列表
            $marketRegions = config('lpstore.market_regions', []);
        }
        
        $defaultRegion = config('lpstore.default_region', 10000002);

        return view('lpstore.index', [
            'user' => $user,
            'isLoggedIn' => $isLoggedIn,
            'activePage' => 'lp-store',
            'marketRegions' => $marketRegions,
            'defaultRegion' => $defaultRegion,
        ]);
    }

    /**
     * API: 获取势力和公司列表
     */
    public function factions()
    {
        try {
            $factions = $this->lpStoreService->getFactions();

            $normalized = array_map(function ($faction) {
                return [
                    'faction_id'   => $faction['faction_id'] ?? null,
                    'name'         => $faction['faction_name'] ?? ($faction['name'] ?? ''),
                    'corporations' => $faction['corporations'] ?? [],
                ];
            }, $factions);

            return response()->json([
                'success' => true,
                'data' => [
                    'factions' => array_values($normalized),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '获取势力数据失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: 获取指定公司的 LP 报价及利润计算
     */
    public function offers(Request $request)
    {
        $request->validate([
            'corporation_id' => 'required|integer|min:1',
            'region_id' => 'nullable|integer',
            'material_price_mode' => 'nullable|string|in:default,buy,sell',
            'output_price_mode' => 'nullable|string|in:default,buy,sell',
        ]);

        $corporationId = (int) $request->input('corporation_id');
        $regionId = (int) $request->input('region_id', config('lpstore.default_region'));
        $materialPriceMode = $request->input('material_price_mode', 'default');
        $outputPriceMode = $request->input('output_price_mode', 'default');

        try {
            // 获取 LP 报价
            $offers = $this->lpStoreService->getOffers($corporationId);
            
            if (empty($offers)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                ]);
            }

            // 收集所有需要价格的物品ID（产出物品 + 材料物品）
            $requiredTypeIds = [];
            foreach ($offers as $offer) {
                $requiredTypeIds[] = $offer['type_id'];
                if (!empty($offer['required_items'])) {
                    foreach ($offer['required_items'] as $item) {
                        $requiredTypeIds[] = $item['type_id'];
                    }
                }
            }
            $requiredTypeIds = array_unique($requiredTypeIds);

            // 获取价格数据（根据计算模式选择不同来源，传入需要价格的物品ID列表）
            $prices = $this->lpStoreService->getPricesForMode($materialPriceMode, $outputPriceMode, $requiredTypeIds);

            // 计算利润
            $calculatedOffers = $this->lpStoreService->calculateOfferProfits($offers, $prices, $materialPriceMode, $outputPriceMode);

            // 按 lp_ratio 降序排序
            usort($calculatedOffers, function ($a, $b) {
                return $b['lp_ratio'] <=> $a['lp_ratio'];
            });

            return response()->json([
                'success' => true,
                'data' => $calculatedOffers,
                'count' => count($calculatedOffers),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '获取 LP 报价失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: 获取物品的历史交易数据
     */
    public function history(Request $request)
    {
        $request->validate([
            'region_id' => 'required|integer|min:1',
            'type_ids' => 'required|string',
        ]);

        $regionId = (int) $request->input('region_id');
        $typeIdsStr = $request->input('type_ids');

        // 解析逗号分隔的 type_ids
        $typeIds = array_filter(
            array_map('intval', explode(',', $typeIdsStr)),
            function ($id) { return $id > 0; }
        );

        if (empty($typeIds)) {
            return response()->json([
                'success' => false,
                'error' => 'type_ids 参数无效',
            ], 400);
        }

        // 限制单次请求的数量，防止滥用
        if (count($typeIds) > 100) {
            $typeIds = array_slice($typeIds, 0, 100);
        }

        try {
            $historyData = $this->lpStoreService->getItemsHistory($regionId, $typeIds);

            return response()->json([
                'success' => true,
                'data' => $historyData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '获取历史数据失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: 获取指定物品的市场订单
     */
    public function orders(Request $request)
    {
        $request->validate([
            'region_id' => 'required|integer|min:1',
            'type_id' => 'required|integer|min:1',
        ]);

        $regionId = (int) $request->input('region_id');
        $typeId = (int) $request->input('type_id');

        try {
            $ordersData = $this->lpStoreService->getOrders($regionId, $typeId);

            return response()->json([
                'success' => true,
                'data' => $ordersData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '获取订单失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}
