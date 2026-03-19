<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class ContractDataController extends Controller
{
    /**
     * 合同类型中文映射
     */
    private const CONTRACT_TYPES = [
        'unknown' => '未知',
        'item_exchange' => '物品交换',
        'auction' => '拍卖',
        'courier' => '快递',
        'loan' => '贷款',
    ];

    /**
     * 合同状态中文映射
     */
    private const CONTRACT_STATUS = [
        'outstanding' => '进行中',
        'in_progress' => '处理中',
        'finished_issuer' => '已完成(发起方)',
        'finished_contractor' => '已完成(接受方)',
        'finished' => '已完成',
        'cancelled' => '已取消',
        'rejected' => '已拒绝',
        'failed' => '已失败',
        'deleted' => '已删除',
        'reversed' => '已撤销',
    ];

    /**
     * 获取合同列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            // 获取合同列表（支持分页）
            $contracts = Cache::remember("contracts_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $allContracts = [];
                $page = 1;
                
                do {
                    $response = Http::withToken($token)
                        ->timeout(15)
                        ->get("{$baseUrl}characters/{$characterId}/contracts/", [
                            'datasource' => 'serenity',
                            'page' => $page
                        ]);
                    
                    if (!$response->ok()) {
                        break;
                    }
                    
                    $data = $response->json();
                    if (empty($data)) {
                        break;
                    }
                    
                    $allContracts = array_merge($allContracts, $data);
                    $page++;
                    
                    $totalPages = (int) $response->header('X-Pages', 1);
                } while ($page <= $totalPages);
                
                return $allContracts;
            });

            if (empty($contracts)) {
                return response()->json([]);
            }

            // 收集需要获取名称的ID
            $issuerIds = array_filter(array_unique(array_column($contracts, 'issuer_id')));
            $assigneeIds = array_filter(array_unique(array_column($contracts, 'assignee_id')));
            $acceptorIds = array_filter(array_unique(array_column($contracts, 'acceptor_id')));
            $allIds = array_unique(array_merge($issuerIds, $assigneeIds, $acceptorIds));
            
            $names = $this->getNames($allIds);

            // 获取位置名称
            $locationIds = array_filter(array_unique(array_merge(
                array_column($contracts, 'start_location_id'),
                array_column($contracts, 'end_location_id')
            )));

            // 构建返回数据
            $result = [];
            foreach ($contracts as $contract) {
                $type = $contract['type'] ?? 'unknown';
                $status = $contract['status'] ?? 'unknown';
                
                $result[] = [
                    'contract_id' => $contract['contract_id'] ?? 0,
                    'type' => $type,
                    'type_name' => self::CONTRACT_TYPES[$type] ?? $type,
                    'status' => $status,
                    'status_name' => self::CONTRACT_STATUS[$status] ?? $status,
                    'title' => $contract['title'] ?? '',
                    'issuer_id' => $contract['issuer_id'] ?? 0,
                    'issuer_name' => $names[$contract['issuer_id'] ?? 0] ?? '',
                    'assignee_id' => $contract['assignee_id'] ?? 0,
                    'assignee_name' => $names[$contract['assignee_id'] ?? 0] ?? '',
                    'acceptor_id' => $contract['acceptor_id'] ?? 0,
                    'acceptor_name' => $names[$contract['acceptor_id'] ?? 0] ?? '',
                    'price' => $contract['price'] ?? 0,
                    'reward' => $contract['reward'] ?? 0,
                    'collateral' => $contract['collateral'] ?? 0,
                    'buyout' => $contract['buyout'] ?? 0,
                    'volume' => $contract['volume'] ?? 0,
                    'days_to_complete' => $contract['days_to_complete'] ?? 0,
                    'date_issued' => $contract['date_issued'] ?? '',
                    'date_expired' => $contract['date_expired'] ?? '',
                    'date_accepted' => $contract['date_accepted'] ?? '',
                    'date_completed' => $contract['date_completed'] ?? '',
                    'for_corporation' => $contract['for_corporation'] ?? false,
                    'availability' => $contract['availability'] ?? 'personal',
                    'start_location_id' => $contract['start_location_id'] ?? 0,
                    'end_location_id' => $contract['end_location_id'] ?? 0,
                ];
            }

            // 按发起时间倒序排序
            usort($result, function($a, $b) {
                return strtotime($b['date_issued']) - strtotime($a['date_issued']);
            });

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取合同数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取合同物品
     */
    public function items(Request $request, $contractId)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $items = Cache::remember("contract_items_{$characterId}_{$contractId}", 300, function () use ($baseUrl, $characterId, $token, $contractId) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/contracts/{$contractId}/items/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return [];
            });

            if (empty($items)) {
                return response()->json([]);
            }

            // 获取物品名称
            $typeIds = array_unique(array_column($items, 'type_id'));
            $typeNames = EveHelper::getNamesByIds($typeIds, 'item');

            // 添加物品名称
            foreach ($items as &$item) {
                $typeId = $item['type_id'] ?? 0;
                $item['type_name'] = $typeNames[$typeId] ?? "物品 #{$typeId}";
            }

            return response()->json($items);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取合同物品失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 批量获取实体名称
     */
    private function getNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $baseUrl = config('esi.base_url');
        $names = [];
        $uncachedIds = [];

        foreach ($ids as $id) {
            if ($id <= 0) continue;
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        if (!empty($uncachedIds)) {
            try {
                $response = Http::timeout(15)
                    ->post("{$baseUrl}universe/names/", array_values(array_unique($uncachedIds)));
                
                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        $id = $item['id'] ?? 0;
                        $name = $item['name'] ?? '';
                        $names[$id] = $name;
                        Cache::put("eve_name_{$id}", $name, 86400);
                    }
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        return $names;
    }
}
