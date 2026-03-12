<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class AssetDataController extends Controller
{
    /**
     * 获取角色资产列表（按地点分组 + 树形层级）
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->eve_character_id) {
            return response()->json([
                'success' => false,
                'error' => 'unauthorized',
                'message' => '未授权，请重新登录',
            ], 401);
        }

        if (empty($user->access_token)) {
            return response()->json([
                'success' => false,
                'error' => 'no_token',
                'message' => '缺少访问令牌',
            ], 401);
        }

        try {
            $assets = $this->fetchAllAssets($user);

            if ($assets === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'eve_api_error',
                    'message' => 'EVE API 请求失败',
                ], 502);
            }

            if (empty($assets)) {
                return response()->json([
                    'success' => true,
                    'data' => ['locations' => []],
                ]);
            }

            $typeIds = array_values(array_unique(array_column($assets, 'type_id')));
            $typeNames = EveHelper::getNamesByIds($typeIds, 'item');
            $typeDetails = $this->getTypeDetails($typeIds);

            $groupIds = [];
            foreach ($typeDetails as $detail) {
                if (!empty($detail['group_id'])) {
                    $groupIds[] = $detail['group_id'];
                }
            }
            $groupIds = array_values(array_unique($groupIds));
            $groupNames = $this->getGroupNames($groupIds);

            $itemIdSet = [];
            foreach ($assets as $asset) {
                $itemIdSet[$asset['item_id']] = true;
            }

            $topLocationIds = [];
            foreach ($assets as $asset) {
                $locType = $asset['location_type'] ?? 'other';
                if ($locType === 'station' || $locType === 'solar_system') {
                    $topLocationIds[$asset['location_id']] = true;
                } elseif ($locType === 'other' && !isset($itemIdSet[$asset['location_id']])) {
                    $topLocationIds[$asset['location_id']] = true;
                }
            }
            $topLocationIds = array_keys($topLocationIds);
            $locationNames = $this->getLocationNames($topLocationIds, $user->access_token);

            $locations = $this->buildAssetTree($assets, $typeNames, $typeDetails, $groupNames, $locationNames, $itemIdSet);

            return response()->json([
                'success' => true,
                'data' => ['locations' => $locations],
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('[Assets] 连接失败：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'connection_timeout',
                'message' => '连接超时，EVE API 可能不可用',
            ], 503);
        } catch (\Exception $e) {
            Log::error('[Assets] 异常：' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'unknown_error',
                'message' => '未知错误：' . $e->getMessage(),
            ], 500);
        }
    }

    private function fetchAllAssets($user): ?array
    {
        $baseUrl = config('esi.base_url') . "characters/{$user->eve_character_id}/assets/";

        $response = Http::timeout(15)
            ->withToken($user->access_token)
            ->get($baseUrl, ['page' => 1]);

        if ($response->failed()) {
            Log::error('[Assets] 资产获取失败', ['status' => $response->status()]);
            if ($response->status() === 401 || $response->status() === 403) {
                return null;
            }
            return null;
        }

        $allAssets = $response->json();
        $totalPages = (int) ($response->header('X-Pages') ?? 1);

        for ($page = 2; $page <= $totalPages; $page++) {
            $pageResponse = Http::timeout(15)
                ->withToken($user->access_token)
                ->get($baseUrl, ['page' => $page]);
            if ($pageResponse->ok()) {
                $allAssets = array_merge($allAssets, $pageResponse->json());
            }
        }

        Log::info('[Assets] 获取资产完成', ['count' => count($allAssets), 'pages' => $totalPages]);
        return $allAssets;
    }

    private function getTypeDetails(array $typeIds): array
    {
        $details = [];
        $uncached = [];

        foreach ($typeIds as $typeId) {
            $cached = Cache::get("eve_type_{$typeId}");
            if ($cached !== null) {
                $details[$typeId] = $cached;
            } else {
                $uncached[] = $typeId;
            }
        }

        foreach ($uncached as $typeId) {
            try {
                $response = Http::timeout(5)
                    ->get(config('esi.base_url') . "universe/types/{$typeId}/");
                if ($response->ok()) {
                    $data = $response->json();
                    $detail = [
                        'volume' => $data['volume'] ?? 0,
                        'group_id' => $data['group_id'] ?? 0,
                    ];
                } else {
                    $detail = ['volume' => 0, 'group_id' => 0];
                }
            } catch (\Exception $e) {
                $detail = ['volume' => 0, 'group_id' => 0];
            }
            Cache::put("eve_type_{$typeId}", $detail, 86400);
            $details[$typeId] = $detail;
        }

        return $details;
    }

    private function getGroupNames(array $groupIds): array
    {
        $names = [];
        $uncached = [];

        foreach ($groupIds as $groupId) {
            $cached = Cache::get("eve_group_{$groupId}");
            if ($cached !== null) {
                $names[$groupId] = $cached;
            } else {
                $uncached[] = $groupId;
            }
        }

        foreach ($uncached as $groupId) {
            try {
                $response = Http::timeout(5)
                    ->get(config('esi.base_url') . "universe/groups/{$groupId}/");
                if ($response->ok()) {
                    $data = $response->json();
                    $name = $data['name'] ?? '';
                } else {
                    $name = '';
                }
            } catch (\Exception $e) {
                $name = '';
            }
            Cache::put("eve_group_{$groupId}", $name, 86400);
            $names[$groupId] = $name;
        }

        return $names;
    }

    private function getLocationNames(array $locationIds, string $accessToken): array
    {
        $names = [];
        if (empty($locationIds)) {
            return $names;
        }

        $normalIds = [];
        $structureIds = [];
        foreach ($locationIds as $id) {
            if ($id > 1000000000000) {
                $structureIds[] = $id;
            } else {
                $normalIds[] = $id;
            }
        }

        if (!empty($normalIds)) {
            try {
                $response = Http::timeout(10)
                    ->post(config('esi.base_url') . 'universe/names/', $normalIds);
                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        $names[$item['id']] = $item['name'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('[Assets] 位置名称批量查询失败：' . $e->getMessage());
            }
        }

        foreach ($structureIds as $structureId) {
            try {
                $response = Http::timeout(5)
                    ->withToken($accessToken)
                    ->get(config('esi.base_url') . "universe/structures/{$structureId}/");
                if ($response->ok()) {
                    $data = $response->json();
                    $names[$structureId] = $data['name'] ?? "建筑 {$structureId}";
                } else {
                    $names[$structureId] = "未知建筑 (ID: {$structureId})";
                }
            } catch (\Exception $e) {
                $names[$structureId] = "未知建筑 (ID: {$structureId})";
            }
        }

        return $names;
    }

    private function buildAssetTree(
        array $assets,
        array $typeNames,
        array $typeDetails,
        array $groupNames,
        array $locationNames,
        array $itemIdSet
    ): array {
        // 步骤 1: 构建 itemMap
        $itemMap = [];
        foreach ($assets as $asset) {
            $typeId = $asset['type_id'];
            $detail = $typeDetails[$typeId] ?? ['volume' => 0, 'group_id' => 0];
            $groupId = $detail['group_id'];

            $itemMap[$asset['item_id']] = [
                'item_id' => $asset['item_id'],
                'type_id' => $typeId,
                'type_name' => $typeNames[$typeId] ?? '未知物品',
                'quantity' => $asset['quantity'] ?? 1,
                'location_flag' => $asset['location_flag'] ?? 'Unknown',
                'location_id' => $asset['location_id'],
                'location_type' => $asset['location_type'] ?? 'other',
                'is_singleton' => $asset['is_singleton'] ?? false,
                'volume' => $detail['volume'],
                'group_name' => $groupNames[$groupId] ?? '',
                'children' => [],
            ];
        }

        // 步骤 2: 挂载子节点，收集顶层节点
        $childOf = []; // parentId => [childItemId, ...]
        $topLevelIds = [];

        foreach ($itemMap as $itemId => $item) {
            if ($item['location_type'] === 'other' && isset($itemMap[$item['location_id']])) {
                $childOf[$item['location_id']][] = $itemId;
            } else {
                $topLevelIds[] = $itemId;
            }
        }

        // 步骤 3: 递归构建节点
        $buildNode = function ($itemId) use (&$buildNode, &$itemMap, &$childOf) {
            $node = $itemMap[$itemId];
            $node['children'] = [];
            if (isset($childOf[$itemId])) {
                foreach ($childOf[$itemId] as $childId) {
                    $node['children'][] = $buildNode($childId);
                }
            }
            // 移除内部字段
            unset($node['location_id'], $node['location_type']);
            return $node;
        };

        // 步骤 4: 按 location_id 分组
        $locationGroups = [];
        foreach ($topLevelIds as $itemId) {
            $locId = $itemMap[$itemId]['location_id'];
            $locationGroups[$locId][] = $itemId;
        }

        $locations = [];
        foreach ($locationGroups as $locId => $itemIds) {
            $items = [];
            foreach ($itemIds as $itemId) {
                $items[] = $buildNode($itemId);
            }

            $countAll = 0;
            $countItems = function ($list) use (&$countItems, &$countAll) {
                foreach ($list as $item) {
                    $countAll++;
                    if (!empty($item['children'])) {
                        $countItems($item['children']);
                    }
                }
            };
            $countItems($items);

            $locations[] = [
                'location_id' => $locId,
                'location_name' => $locationNames[$locId] ?? "未知位置 (ID: {$locId})",
                'item_count' => $countAll,
                'items' => $items,
            ];
        }

        // 按物品数量降序排列
        usort($locations, function ($a, $b) {
            return $b['item_count'] - $a['item_count'];
        });

        return $locations;
    }
}
