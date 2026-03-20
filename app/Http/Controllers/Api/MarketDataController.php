<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketDataController extends Controller
{
    private MarketService $marketService;

    public function __construct(MarketService $marketService)
    {
        $this->marketService = $marketService;
    }

    /**
     * 获取市场分组树
     * GET /api/public/market/groups
     */
    public function groups(Request $request)
    {
        // 市场分组树构建需要从 ESI 获取 3000+ 分组和数万物品名称
        // 首次构建时可能需要较长时间，允许最多 120 秒
        set_time_limit(120);

        $tree = $this->marketService->getMarketGroupsTree();

        $response = response()->json([
            'success' => true,
            'data' => $tree,
            'cached' => !empty($tree),
        ]);

        // 添加 HTTP 缓存头，浏览器缓存 1 小时
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->header('Vary', 'Accept-Encoding');

        return $response;
    }

    /**
     * 搜索物品（模糊匹配）
     * GET /api/public/market/search?q=xxx
     */
    public function searchItems(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 1) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $results = $this->marketService->searchItems($query, 50);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * 获取所有星域列表
     * GET /api/public/market/regions
     */
    public function regions(Request $request)
    {
        $regions = $this->marketService->getAllRegions();

        return response()->json([
            'success' => true,
            'data' => $regions,
        ]);
    }

    /**
     * 获取区域活跃物品类型
     * GET /api/public/market/active-types?region_id=X
     */
    public function activeTypes(Request $request)
    {
        $regionId = $request->input('region_id', 'all');
        $typeIds = $this->marketService->getActiveTypeIds($regionId);

        return response()->json([
            'success' => true,
            'data' => $typeIds,
        ]);
    }

    /**
     * 获取市场订单
     * GET /api/public/market/orders?region_id=X&type_id=Y
     */
    public function orders(Request $request)
    {
        $request->validate([
            'region_id' => 'required',
            'type_id' => 'required|integer',
        ]);

        $regionId = $request->input('region_id');
        $typeId = (int) $request->input('type_id');

        $orders = $this->marketService->getOrders($regionId, $typeId);

        // 为订单计算到期时间
        $orders['sell'] = $this->marketService->enrichOrdersWithExpires($orders['sell']);
        $orders['buy'] = $this->marketService->enrichOrdersWithExpires($orders['buy']);

        // 为订单添加位置信息
        $orders['sell'] = $this->marketService->enrichOrdersWithLocation($orders['sell']);
        $orders['buy'] = $this->marketService->enrichOrdersWithLocation($orders['buy']);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * 获取价格历史
     * GET /api/public/market/history?region_id=X&type_id=Y
     */
    public function history(Request $request)
    {
        $request->validate([
            'region_id' => 'required',
            'type_id' => 'required|integer',
        ]);

        $regionId = $request->input('region_id');
        $typeId = (int) $request->input('type_id');

        $history = $this->marketService->getPriceHistory($regionId, $typeId);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * 获取物品详情
     * GET /api/public/market/types/{id}
     */
    public function typeDetail(Request $request, string $id)
    {
        $detail = $this->marketService->getTypeDetail((int) $id);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => '物品不存在',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    /**
     * 获取角色市场订单（需认证）
     * GET /api/market/character-orders
     */
    public function characterOrders(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->access_token) {
            return response()->json([
                'success' => false,
                'message' => '未授权',
            ], 401);
        }

        $orders = $this->marketService->getCharacterOrders(
            $user->access_token,
            $user->eve_character_id
        );

        // 为订单添加到期时间、位置信息和物品名称
        $orders = $this->marketService->enrichOrdersWithExpires($orders);
        $orders = $this->marketService->enrichOrdersWithLocation($orders);
        $orders = $this->marketService->enrichOrdersWithTypeName($orders);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * 获取当前角色订单ID列表（用于高亮）
     * GET /api/market/my-order-ids
     */
    public function myOrderIds(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->access_token) {
            return response()->json([
                'success' => false,
                'message' => '未授权',
            ], 401);
        }

        $orders = $this->marketService->getCharacterOrders(
            $user->access_token,
            $user->eve_character_id
        );

        $orderIds = array_column($orders, 'order_id');

        return response()->json([
            'success' => true,
            'data' => $orderIds,
        ]);
    }
}
