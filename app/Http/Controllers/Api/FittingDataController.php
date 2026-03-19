<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class FittingDataController extends Controller
{
    /**
     * 槽位标志中文映射
     */
    private const FLAG_NAMES = [
        'Cargo' => '货柜仓',
        'DroneBay' => '无人机仓',
        'FighterBay' => '舰载机仓',
        'HiSlot0' => '高槽 1',
        'HiSlot1' => '高槽 2',
        'HiSlot2' => '高槽 3',
        'HiSlot3' => '高槽 4',
        'HiSlot4' => '高槽 5',
        'HiSlot5' => '高槽 6',
        'HiSlot6' => '高槽 7',
        'HiSlot7' => '高槽 8',
        'MedSlot0' => '中槽 1',
        'MedSlot1' => '中槽 2',
        'MedSlot2' => '中槽 3',
        'MedSlot3' => '中槽 4',
        'MedSlot4' => '中槽 5',
        'MedSlot5' => '中槽 6',
        'MedSlot6' => '中槽 7',
        'MedSlot7' => '中槽 8',
        'LoSlot0' => '低槽 1',
        'LoSlot1' => '低槽 2',
        'LoSlot2' => '低槽 3',
        'LoSlot3' => '低槽 4',
        'LoSlot4' => '低槽 5',
        'LoSlot5' => '低槽 6',
        'LoSlot6' => '低槽 7',
        'LoSlot7' => '低槽 8',
        'RigSlot0' => '改装件 1',
        'RigSlot1' => '改装件 2',
        'RigSlot2' => '改装件 3',
        'SubSystemSlot0' => '子系统 1',
        'SubSystemSlot1' => '子系统 2',
        'SubSystemSlot2' => '子系统 3',
        'SubSystemSlot3' => '子系统 4',
        'SubSystemSlot4' => '子系统 5',
        'ServiceSlot0' => '服务槽 1',
        'ServiceSlot1' => '服务槽 2',
        'ServiceSlot2' => '服务槽 3',
        'ServiceSlot3' => '服务槽 4',
        'ServiceSlot4' => '服务槽 5',
        'ServiceSlot5' => '服务槽 6',
        'ServiceSlot6' => '服务槽 7',
        'ServiceSlot7' => '服务槽 8',
    ];

    /**
     * 获取装配列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $fittings = Cache::remember("fittings_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/fittings/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return [];
            });

            if (empty($fittings)) {
                return response()->json([]);
            }

            // 收集所有物品类型ID
            $typeIds = [];
            foreach ($fittings as $fitting) {
                $typeIds[] = $fitting['ship_type_id'] ?? 0;
                foreach ($fitting['items'] ?? [] as $item) {
                    $typeIds[] = $item['type_id'] ?? 0;
                }
            }
            $typeIds = array_filter(array_unique($typeIds));
            $typeNames = EveHelper::getNamesByIds($typeIds, 'item');

            // 构建返回数据
            $result = [];
            foreach ($fittings as $fitting) {
                $shipTypeId = $fitting['ship_type_id'] ?? 0;
                
                // 按槽位分组物品
                $slots = [
                    'high' => [],
                    'med' => [],
                    'low' => [],
                    'rig' => [],
                    'subsystem' => [],
                    'service' => [],
                    'drone' => [],
                    'cargo' => [],
                    'fighter' => [],
                ];

                foreach ($fitting['items'] ?? [] as $item) {
                    $flag = $item['flag'] ?? '';
                    $typeId = $item['type_id'] ?? 0;
                    $quantity = $item['quantity'] ?? 1;
                    
                    $itemData = [
                        'type_id' => $typeId,
                        'type_name' => $typeNames[$typeId] ?? "物品 #{$typeId}",
                        'quantity' => $quantity,
                        'flag' => $flag,
                        'flag_name' => self::FLAG_NAMES[$flag] ?? $flag,
                    ];

                    if (strpos($flag, 'HiSlot') === 0) {
                        $slots['high'][] = $itemData;
                    } elseif (strpos($flag, 'MedSlot') === 0) {
                        $slots['med'][] = $itemData;
                    } elseif (strpos($flag, 'LoSlot') === 0) {
                        $slots['low'][] = $itemData;
                    } elseif (strpos($flag, 'RigSlot') === 0) {
                        $slots['rig'][] = $itemData;
                    } elseif (strpos($flag, 'SubSystemSlot') === 0) {
                        $slots['subsystem'][] = $itemData;
                    } elseif (strpos($flag, 'ServiceSlot') === 0) {
                        $slots['service'][] = $itemData;
                    } elseif ($flag === 'DroneBay') {
                        $slots['drone'][] = $itemData;
                    } elseif ($flag === 'Cargo') {
                        $slots['cargo'][] = $itemData;
                    } elseif ($flag === 'FighterBay') {
                        $slots['fighter'][] = $itemData;
                    }
                }

                // 对每个槽位按flag排序
                foreach ($slots as &$slotItems) {
                    usort($slotItems, fn($a, $b) => strcmp($a['flag'], $b['flag']));
                }

                $result[] = [
                    'fitting_id' => $fitting['fitting_id'] ?? 0,
                    'name' => $fitting['name'] ?? '',
                    'description' => $fitting['description'] ?? '',
                    'ship_type_id' => $shipTypeId,
                    'ship_type_name' => $typeNames[$shipTypeId] ?? "舰船 #{$shipTypeId}",
                    'items' => $fitting['items'] ?? [],
                    'slots' => $slots,
                ];
            }

            // 按名称排序
            usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取装配数据失败: ' . $e->getMessage()], 500);
        }
    }
}
