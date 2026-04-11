<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FittingDataService;
use App\Services\FittingOfficialDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 装配模拟器数据 API（公开访问）
 */
class FittingSimulatorDataController extends Controller
{
    private FittingDataService $fittingService;
    private FittingOfficialDataService $officialFittingService;

    public function __construct(FittingDataService $fittingService, FittingOfficialDataService $officialFittingService)
    {
        $this->fittingService = $fittingService;
        $this->officialFittingService = $officialFittingService;
    }

    /**
     * 获取舰船分类列表
     */
    public function categories()
    {
        return response()->json($this->fittingService->getShipCategories());
    }

    public function shipCategoryTree()
    {
        $tree = $this->officialFittingService->isDatabaseReady()
            ? $this->officialFittingService->getShipCategoryTree()
            : $this->fittingService->getShipCategoryTree();

        return response()->json($tree);
    }

    /**
     * 获取指定组内的舰船列表
     */
    public function shipsByGroup(int $groupId)
    {
        return response()->json($this->fittingService->getShipsByGroup($groupId));
    }

    /**
     * 获取指定组内指定势力的舰船列表
     */
    public function shipsByGroupAndFaction(int $groupId, int $factionId)
    {
        return response()->json($this->fittingService->getShipsByGroupAndFaction($groupId, $factionId));
    }

    /**
     * 获取舰船详情
     */
    public function shipDetails(int $typeId)
    {
        $ship = $this->officialFittingService->isDatabaseReady()
            ? $this->officialFittingService->getShipDetails($typeId)
            : $this->fittingService->getShipDetails($typeId);
        
        if (!$ship) {
            return response()->json(['error' => '舰船不存在'], 404);
        }

        return response()->json($ship);
    }

    /**
     * 获取物品详情（装备/弹药等）
     */
    public function typeDetails(int $typeId)
    {
        $type = $this->fittingService->getTypeDetails($typeId);
        
        if (!$type) {
            return response()->json(['error' => '物品不存在'], 404);
        }

        if ($this->officialFittingService->isDatabaseReady()) {
            $officialType = $this->officialFittingService->findType($typeId);
            if ($officialType && !empty($officialType['slot_type'])) {
                $type['slot'] = $officialType['slot_type'];
                $type['slot_source'] = $officialType['slot_source'] ?? $type['slot_source'] ?? null;
                $type['volume'] = (float) ($officialType['volume'] ?? $type['volume'] ?? 0);
                $type['image_url'] = "https://image.evepc.163.com/Type/{$typeId}_64.png";
            }
        }

        return response()->json($type);
    }

    /**
     * 搜索装备
     */
    public function searchModules(Request $request)
    {
        $query = $request->input('q', '');
        $slot = $request->input('slot', null);
        $limit = (int) $request->input('limit', 50);

        // 验证 slot 参数
        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots, true)) {
            $slot = null;
        }

        $results = $this->officialFittingService->isDatabaseReady()
            ? $this->officialFittingService->searchModules((string) $query, $slot, $limit)
            : $this->fittingService->searchModules((string) $query, $slot, $limit);

        return response()->json([
            'query' => $query,
            'slot' => $slot,
            'total' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * 获取物品图片 URL
     */
    public function imageUrl(int $typeId, Request $request)
    {
        $size = $request->input('size', 128);
        $url = $this->fittingService->getImageUrl($typeId, $size);

        return response()->json([
            'type_id' => $typeId,
            'url' => $url,
        ]);
    }

    /**
     * 代理获取物品图片（本地缓存优先）
     * 如果本地有缓存，直接返回图片文件
     * 如果没有，从 CDN 获取并缓存
     */
    public function getImage(int $typeId, Request $request)
    {
        $size = $request->input('size', 128);
        $localPath = public_path("images/types/{$typeId}_{$size}.png");

        // 如果本地有缓存，直接返回
        if (file_exists($localPath)) {
            return response()->file($localPath, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // 从 CDN 获取
        $cdnUrl = $this->fittingService->getRemoteImageUrl($typeId, (int)$size);
        
        try {
            $imageData = \Illuminate\Support\Facades\Http::timeout(10)->get($cdnUrl)->body();
            
            // 缓存到本地
            if (!is_dir(public_path('images/types'))) {
                mkdir(public_path('images/types'), 0755, true);
            }
            file_put_contents($localPath, $imageData);

            return response($imageData, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);

        } catch (\Exception $e) {
            // 返回默认图片或错误
            return response()->json(['error' => '图片获取失败'], 404);
        }
    }

    /**
     * 批量获取多个物品的详情
     */
    public function batchTypeDetails(Request $request)
    {
        $typeIds = $request->input('type_ids', []);
        
        if (!is_array($typeIds) || empty($typeIds)) {
            return response()->json(['error' => '请提供 type_ids 数组'], 400);
        }

        // 限制批量数量
        $typeIds = array_slice($typeIds, 0, 100);

        $results = [];
        foreach ($typeIds as $typeId) {
            $type = $this->fittingService->getTypeDetails((int)$typeId);
            if ($type) {
                $results[$typeId] = $type;
            }
        }

        return response()->json($results);
    }

    /**
     * 获取属性定义列表
     */
    public function attributeDefinitions()
    {
        return response()->json($this->fittingService->getAttributeDefinitions());
    }

    /**
     * 获取装备分类列表
     */
    public function moduleCategories()
    {
        return response()->json($this->fittingService->getModuleCategories());
    }

    /**
     * 获取指定分组的装备列表
     */
    public function modulesByGroup(int $groupId, Request $request)
    {
        $slot = $request->input('slot', null);
        
        // 验证 slot 参数
        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots)) {
            $slot = null;
        }

        $modules = $this->fittingService->getModulesByGroup($groupId, $slot);

        return response()->json($modules);
    }

    /**
     * 获取基于eve_items.json四级分类的装备分类树
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function moduleCategoryTree(Request $request)
    {
        $slot = $request->input('slot', null);
        
        // 验证 slot 参数
        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots)) {
            $slot = null;
        }

        $tree = $this->fittingService->getModuleCategoryTree($slot);

        return response()->json($tree);
    }

    public function officialSubsetSummary()
    {
        if (!$this->officialFittingService->isDatabaseReady()) {
            return response()->json(['error' => '官方装配子集尚未生成'], 503);
        }

        return response()->json($this->officialFittingService->getSummary());
    }

    public function officialModuleCategoryTree(Request $request)
    {
        if (!$this->officialFittingService->isDatabaseReady()) {
            return response()->json(['error' => '官方装配子集尚未生成'], 503);
        }

        $slot = $request->input('slot', null);
        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots, true)) {
            $slot = null;
        }

        return response()->json($this->officialFittingService->getModuleCategoryTree($slot, $this->moduleFilterOptions($request)));
    }

    /**
     * 获取指定分类路径下的装备列表
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function modulesByCategoryPath(Request $request)
    {
        $pathJson = $request->input('path', '[]');
        $slot = $request->input('slot', null);
        $path = json_decode($pathJson, true);
        
        if (!is_array($path) || empty($path)) {
            return response()->json(['error' => '请提供有效的分类路径'], 400);
        }

        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots, true)) {
            $slot = null;
        }

        $modules = $this->fittingService->getModulesByCategoryPath($path, $slot);

        return response()->json([
            'path' => $path,
            'slot' => $slot,
            'total' => count($modules),
            'modules' => $modules,
        ]);
    }

    public function officialModulesByCategoryPath(Request $request)
    {
        if (!$this->officialFittingService->isDatabaseReady()) {
            return response()->json(['error' => '官方装配子集尚未生成'], 503);
        }

        $pathJson = $request->input('path', '[]');
        $slot = $request->input('slot', null);
        $path = json_decode($pathJson, true);

        if (!is_array($path) || empty($path)) {
            return response()->json(['error' => '请提供有效的分类路径'], 400);
        }

        $validSlots = ['high', 'med', 'low', 'rig', 'drone'];
        if ($slot && !in_array($slot, $validSlots, true)) {
            $slot = null;
        }

        $modules = $this->officialFittingService->getModulesByCategoryPath($path, $slot, $this->moduleFilterOptions($request));

        return response()->json([
            'path' => $path,
            'slot' => $slot,
            'total' => count($modules),
            'modules' => $modules,
        ]);
    }

    public function shipsByCategoryPath(Request $request)
    {
        $pathJson = $request->input('path', '[]');
        $path = json_decode($pathJson, true);

        if (!is_array($path) || empty($path)) {
            return response()->json(['error' => '请提供有效的分类路径'], 400);
        }

        $ships = $this->officialFittingService->isDatabaseReady()
            ? $this->officialFittingService->getShipsByCategoryPath($path)
            : $this->fittingService->getShipsByCategoryPath($path);

        return response()->json([
            'path' => $path,
            'total' => count($ships),
            'ships' => $ships,
        ]);
    }

    private function moduleFilterOptions(Request $request): array
    {
        return [
            'ship_filter' => $request->boolean('filter_ship'),
            'resource_filter' => $request->boolean('filter_resources'),
            'ship_type_id' => (int) $request->input('ship_type_id', 0),
            'ship_group_id' => (int) $request->input('ship_group_id', 0),
            'ship_is_capital_size' => $request->boolean('ship_is_capital_size') || (int) $request->input('ship_is_capital_size', 0) === 1,
            'slot_high' => (int) $request->input('slot_high', 0),
            'slot_med' => (int) $request->input('slot_med', 0),
            'slot_low' => (int) $request->input('slot_low', 0),
            'slot_rig' => (int) $request->input('slot_rig', 0),
            'ship_rig_size' => (int) $request->input('ship_rig_size', 0),
            'drone_bay_capacity' => (float) $request->input('drone_bay_capacity', 0),
            'remaining_cpu' => (float) $request->input('remaining_cpu', 0),
            'remaining_power' => (float) $request->input('remaining_power', 0),
            'remaining_calibration' => (float) $request->input('remaining_calibration', 0),
            'remaining_drone_bay' => (float) $request->input('remaining_drone_bay', 0),
            'remaining_drone_bandwidth' => (float) $request->input('remaining_drone_bandwidth', 0),
        ];
    }
}
