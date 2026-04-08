<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FittingDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 装配模拟器数据 API（公开访问）
 */
class FittingSimulatorDataController extends Controller
{
    private FittingDataService $fittingService;

    public function __construct(FittingDataService $fittingService)
    {
        $this->fittingService = $fittingService;
    }

    /**
     * 获取舰船分类列表
     */
    public function categories()
    {
        return response()->json($this->fittingService->getShipCategories());
    }

    /**
     * 获取指定组内的舰船列表
     */
    public function shipsByGroup(int $groupId)
    {
        return response()->json($this->fittingService->getShipsByGroup($groupId));
    }

    /**
     * 获取舰船详情
     */
    public function shipDetails(int $typeId)
    {
        $ship = $this->fittingService->getShipDetails($typeId);
        
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

        return response()->json($type);
    }

    /**
     * 搜索装备
     */
    public function searchModules(Request $request)
    {
        $query = $request->input('q', '');
        $slot = $request->input('slot', null);
        $limit = $request->input('limit', 50);

        // 验证 slot 参数
        $validSlots = ['high', 'med', 'low', 'rig'];
        if ($slot && !in_array($slot, $validSlots)) {
            $slot = null;
        }

        $results = $this->fittingService->searchModules((string)$query, $slot, $limit);

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
        $localPath = public_path("images/types/{$typeId}.png");

        // 如果本地有缓存，直接返回
        if (file_exists($localPath)) {
            return response()->file($localPath, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // 从 CDN 获取
        $cdnUrl = "https://image.evepc.163.com/Render/{$typeId}_{$size}.png";
        
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

    /**
     * 获取指定分类路径下的装备列表
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function modulesByCategoryPath(Request $request)
    {
        $pathJson = $request->input('path', '[]');
        $path = json_decode($pathJson, true);
        
        if (!is_array($path) || empty($path)) {
            return response()->json(['error' => '请提供有效的分类路径'], 400);
        }

        $modules = $this->fittingService->getModulesByCategoryPath($path);

        return response()->json([
            'path' => $path,
            'total' => count($modules),
            'modules' => $modules,
        ]);
    }
}