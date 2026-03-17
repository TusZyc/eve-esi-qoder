<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\AssetDataService;
use App\Services\ApiErrorHandler;
use App\Exceptions\EveApiException;

/**
 * 资产数据 API 控制器
 * 
 * 委托 AssetDataService 处理业务逻辑
 */
class AssetDataController extends Controller
{
    protected AssetDataService $assetService;

    public function __construct(AssetDataService $assetService)
    {
        $this->assetService = $assetService;
    }

    /**
     * 第一步：获取按星系分组的位置列表
     */
    public function locations(Request $request)
    {
        try {
            $user = ApiErrorHandler::requireAuth($request);

            $assets = $this->assetService->getCachedAssets($user);
            if ($assets === null) {
                throw new EveApiException('eve_api_error', 'EVE API 请求失败', 502);
            }
            if (empty($assets)) {
                return ApiErrorHandler::success(['solar_systems' => []]);
            }

            // 构建资产映射
            [$itemIdSet, $childOf, $topItems] = $this->assetService->buildAssetMaps($assets);
            [$locationCounts, $locationTypes] = $this->assetService->countLocationItems($topItems);

            // 过滤掉 0 物品的位置
            $locationCounts = array_filter($locationCounts, fn($count) => $count > 0);

            if (empty($locationCounts)) {
                return ApiErrorHandler::success(['solar_systems' => []]);
            }

            // 获取位置信息
            $locationIds = array_keys($locationCounts);
            $locationInfo = $this->assetService->getLocationInfo($locationIds, $locationTypes, $user->access_token);

            // 按星系分组
            $systemGroups = [];
            foreach ($locationCounts as $locId => $count) {
                $info = $locationInfo[$locId] ?? ['name' => "未知位置 (ID: {$locId})", 'system_id' => 0];
                $systemId = $info['system_id'] ?? 0;

                if (!isset($systemGroups[$systemId])) {
                    $systemGroups[$systemId] = [
                        'system_id' => $systemId,
                        'locations' => [],
                    ];
                }
                $systemGroups[$systemId]['locations'][] = [
                    'location_id' => $locId,
                    'location_name' => $info['name'],
                    'item_count' => $count,
                ];
            }

            // 获取星系名称
            $systemIds = array_filter(array_keys($systemGroups), fn($id) => $id > 0);
            $systemNames = $this->assetService->getSolarSystemNames(array_values($systemIds));

            // 构建最终结果
            $solarSystems = [];
            foreach ($systemGroups as $systemId => $group) {
                $systemName = $systemNames[$systemId] ?? ($systemId > 0 ? "未知星系 (ID: {$systemId})" : '未知位置');
                $totalItems = array_sum(array_column($group['locations'], 'item_count'));

                usort($group['locations'], fn($a, $b) => $b['item_count'] - $a['item_count']);

                $solarSystems[] = [
                    'system_id' => $systemId,
                    'system_name' => $systemName,
                    'total_items' => $totalItems,
                    'locations' => $group['locations'],
                ];
            }

            usort($solarSystems, fn($a, $b) => $b['total_items'] - $a['total_items']);

            return ApiErrorHandler::success(['solar_systems' => $solarSystems]);

        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('[Assets] 连接失败：' . $e->getMessage());
            return ApiErrorHandler::error('connection_timeout', '连接超时', 503);
        } catch (\Exception $e) {
            Log::error('[Assets] 异常：' . $e->getMessage());
            return ApiErrorHandler::error('unknown_error', $e->getMessage(), 500);
        }
    }

    /**
     * 全局搜索：轻量级，只查类型名
     */
    public function search(Request $request)
    {
        $keyword = trim($request->get('q', ''));
        if (mb_strlen($keyword) < 2) {
            return ApiErrorHandler::success(['results' => []]);
        }

        try {
            $user = ApiErrorHandler::requireAuth($request);

            $assets = $this->assetService->getCachedAssets($user);
            if ($assets === null || empty($assets)) {
                return ApiErrorHandler::success(['results' => []]);
            }

            // 构建资产映射
            [$itemIdSet, $childOf, $topItems] = $this->assetService->buildAssetMaps($assets);

            // 创建 assetMap
            $assetMap = [];
            foreach ($assets as $asset) {
                $assetMap[$asset['item_id']] = $asset;
            }

            // 找出每个物品的根 location_id
            $rootLocCache = [];
            $findRootLoc = function ($asset) use (&$findRootLoc, &$itemIdSet, &$assetMap, &$rootLocCache) {
                $itemId = $asset['item_id'];
                if (isset($rootLocCache[$itemId])) {
                    return $rootLocCache[$itemId];
                }
                $locType = $asset['location_type'] ?? 'other';
                $isChild = (in_array($locType, ['item', 'other']) && isset($itemIdSet[$asset['location_id']]));
                if ($isChild && isset($assetMap[$asset['location_id']])) {
                    $rootLocCache[$itemId] = $findRootLoc($assetMap[$asset['location_id']]);
                } else {
                    $rootLocCache[$itemId] = $asset['location_id'];
                }
                return $rootLocCache[$itemId];
            };

            // 获取类型名称
            $typeIds = array_values(array_unique(array_column($assets, 'type_id')));
            $typeNames = $this->assetService->getTypeNamesChinese($typeIds);

            // 按关键词过滤
            $kw = mb_strtolower($keyword);
            $locationResults = [];
            foreach ($assets as $asset) {
                $typeName = $typeNames[$asset['type_id']] ?? '';
                if ($typeName && mb_strpos(mb_strtolower($typeName), $kw) !== false) {
                    $rootLoc = $findRootLoc($asset);
                    if (!isset($locationResults[$rootLoc])) {
                        $locationResults[$rootLoc] = [];
                    }
                    $locationResults[$rootLoc][] = [
                        'type_name' => $typeName,
                        'quantity' => $asset['quantity'] ?? 1,
                    ];
                }
            }

            if (empty($locationResults)) {
                return ApiErrorHandler::success(['results' => []]);
            }

            // 获取位置名称
            $locIds = array_keys($locationResults);
            $locNames = [];
            foreach ($locIds as $locId) {
                $cached = Cache::get("eve_locinfo_{$locId}");
                if ($cached) {
                    $locNames[$locId] = $cached['name'] ?? "位置 {$locId}";
                } else {
                    $locNames[$locId] = "位置 {$locId}";
                }
            }

            // 合并同类型物品并构建结果
            $results = [];
            foreach ($locationResults as $locId => $items) {
                $merged = [];
                foreach ($items as $item) {
                    $key = $item['type_name'];
                    if (!isset($merged[$key])) {
                        $merged[$key] = ['type_name' => $item['type_name'], 'quantity' => 0];
                    }
                    $merged[$key]['quantity'] += $item['quantity'];
                }
                $mergedItems = array_values($merged);
                usort($mergedItems, fn($a, $b) => $b['quantity'] - $a['quantity']);
                $results[] = [
                    'location_id' => $locId,
                    'location_name' => $locNames[$locId],
                    'items' => $mergedItems,
                ];
            }

            usort($results, fn($a, $b) => count($b['items']) - count($a['items']));

            return ApiErrorHandler::success(['results' => $results]);

        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            Log::error('[Assets] 搜索异常：' . $e->getMessage());
            return ApiErrorHandler::error('unknown_error', $e->getMessage(), 500);
        }
    }

    /**
     * 第二步：获取某个位置的物品详情（懒加载）
     */
    public function locationItems(Request $request, $locationId)
    {
        try {
            $user = ApiErrorHandler::requireAuth($request);

            set_time_limit(120);
            $locationId = (int) $locationId;

            // 检查位置物品缓存
            $locCacheKey = "assets_loc_{$user->eve_character_id}_{$locationId}";
            $cached = Cache::get($locCacheKey);
            if ($cached !== null) {
                return ApiErrorHandler::success(['items' => $cached]);
            }

            $assets = $this->assetService->getCachedAssets($user);
            if ($assets === null || empty($assets)) {
                throw new EveApiException('no_data', '无资产数据', 502);
            }

            // 构建映射
            $assetMap = [];
            $itemIdSet = [];
            foreach ($assets as $asset) {
                $assetMap[$asset['item_id']] = $asset;
                $itemIdSet[$asset['item_id']] = true;
            }

            $childOf = [];
            $topItemsAtLoc = [];
            foreach ($assets as $asset) {
                $locType = $asset['location_type'] ?? 'other';
                $isChild = (in_array($locType, ['item', 'other']) && isset($itemIdSet[$asset['location_id']]));
                if ($isChild) {
                    $childOf[$asset['location_id']][] = $asset['item_id'];
                } else {
                    if ($asset['location_id'] == $locationId) {
                        $topItemsAtLoc[] = $asset['item_id'];
                    }
                }
            }

            // 收集该位置所有相关物品ID
            $allItemIds = [];
            $collectAll = function ($itemId) use (&$collectAll, &$childOf, &$allItemIds) {
                $allItemIds[] = $itemId;
                if (isset($childOf[$itemId])) {
                    foreach ($childOf[$itemId] as $childId) {
                        $collectAll($childId);
                    }
                }
            };
            foreach ($topItemsAtLoc as $itemId) {
                $collectAll($itemId);
            }

            // 获取类型信息
            $typeIds = [];
            foreach ($allItemIds as $itemId) {
                if (isset($assetMap[$itemId])) {
                    $typeIds[] = $assetMap[$itemId]['type_id'];
                }
            }
            $typeIds = array_values(array_unique($typeIds));

            $typeNames = $this->assetService->getTypeNamesChinese($typeIds);
            $typeDetails = $this->assetService->getTypeDetails($typeIds);

            $groupIds = [];
            foreach ($typeDetails as $detail) {
                if (!empty($detail['group_id'])) {
                    $groupIds[] = $detail['group_id'];
                }
            }
            $groupIds = array_values(array_unique($groupIds));
            $groupInfo = $this->assetService->getGroupNames($groupIds);

            // 构建树
            $buildNode = function ($itemId) use (&$buildNode, &$assetMap, &$childOf, &$typeNames, &$typeDetails, &$groupInfo) {
                $asset = $assetMap[$itemId];
                $typeId = $asset['type_id'];
                $detail = $typeDetails[$typeId] ?? ['volume' => 0, 'group_id' => 0];
                $groupId = $detail['group_id'];
                $gi = $groupInfo[$groupId] ?? ['name' => '', 'category_id' => 0];

                $node = [
                    'item_id' => $asset['item_id'],
                    'type_id' => $typeId,
                    'type_name' => $typeNames[$typeId] ?? "未知物品 ({$typeId})",
                    'quantity' => $asset['quantity'] ?? 1,
                    'location_flag' => $asset['location_flag'] ?? 'Unknown',
                    'is_singleton' => $asset['is_singleton'] ?? false,
                    'volume' => $detail['volume'],
                    'group_name' => $gi['name'],
                    'category_id' => $gi['category_id'],
                    'children' => [],
                ];

                if (isset($childOf[$itemId])) {
                    foreach ($childOf[$itemId] as $childId) {
                        $node['children'][] = $buildNode($childId);
                    }
                }

                return $node;
            };

            $items = [];
            foreach ($topItemsAtLoc as $itemId) {
                $items[] = $buildNode($itemId);
            }

            // 缓存15分钟
            Cache::put($locCacheKey, $items, 900);

            return ApiErrorHandler::success(['items' => $items]);

        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            Log::error("[Assets] 位置 {$locationId} 物品加载异常：" . $e->getMessage());
            return ApiErrorHandler::error('unknown_error', $e->getMessage(), 500);
        }
    }
}
