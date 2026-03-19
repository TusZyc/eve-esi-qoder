<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class WalletDataController extends Controller
{
    /**
     * 流水类型中文映射
     */
    private const JOURNAL_REF_TYPES = [
        'agent_donation' => '代理人捐赠',
        'agent_location_services' => '代理人定位服务',
        'agent_miscellaneous' => '代理人杂项',
        'agent_mission_collateral_paid' => '任务抵押金支付',
        'agent_mission_collateral_refunded' => '任务抵押金退还',
        'agent_mission_reward' => '任务奖励',
        'agent_mission_reward_corporation_tax' => '任务奖励军团税',
        'agent_mission_time_bonus_reward' => '任务时间奖励',
        'agent_mission_time_bonus_reward_corporation_tax' => '任务时间奖励军团税',
        'agent_security_services' => '代理人安全服务',
        'agent_services_rendered' => '代理人服务费',
        'agents_preward' => '代理人预付奖励',
        'alliance_maintainance_fee' => '联盟维护费',
        'bounty_prize' => '赏金奖励',
        'bounty_prize_corporation_tax' => '赏金军团税',
        'bounty_prizes' => '赏金奖励',
        'bounty_reimbursement' => '赏金报销',
        'brokers_fee' => '经纪费',
        'clone_activation' => '克隆激活',
        'clone_transfer' => '克隆转移',
        'contract_auction_bid' => '合同拍卖出价',
        'contract_auction_bid_corp' => '军团合同拍卖出价',
        'contract_auction_bid_refund' => '拍卖出价退款',
        'contract_auction_sold' => '拍卖售出',
        'contract_brokers_fee' => '合同经纪费',
        'contract_brokers_fee_corp' => '军团合同经纪费',
        'contract_collateral' => '合同抵押',
        'contract_collateral_deposited_corp' => '军团合同抵押存入',
        'contract_collateral_payout' => '合同抵押支付',
        'contract_collateral_refund' => '合同抵押退款',
        'contract_deposit' => '合同保证金',
        'contract_deposit_corp' => '军团合同保证金',
        'contract_deposit_refund' => '合同保证金退款',
        'contract_deposit_sales_tax' => '合同销售税',
        'contract_price' => '合同价格',
        'contract_price_payment_corp' => '军团合同价格支付',
        'contract_reversal' => '合同撤销',
        'contract_reward' => '合同奖励',
        'contract_reward_deposited' => '合同奖励存入',
        'contract_reward_deposited_corp' => '军团合同奖励存入',
        'contract_reward_refund' => '合同奖励退款',
        'contract_sales_tax' => '合同销售税',
        'copying' => '蓝图复制',
        'corporate_reward_payout' => '军团奖励支付',
        'corporate_reward_tax' => '军团奖励税',
        'corporation_account_withdrawal' => '军团账户提取',
        'corporation_bulk_payment' => '军团批量支付',
        'corporation_dividend_payment' => '军团分红支付',
        'corporation_liquidation' => '军团清算',
        'corporation_logo_change_cost' => '军团Logo变更费',
        'corporation_payment' => '军团支付',
        'corporation_registration_fee' => '军团注册费',
        'courier_mission_escrow' => '快递任务托管',
        'cspa' => 'CSPA费用',
        'cspacharge' => 'CSPA费用',
        'daily_challenge_reward' => '每日挑战奖励',
        'datacore_fee' => '数据核心费',
        'docking_fee' => '停靠费',
        'duel_wager_escrow' => '决斗押金托管',
        'duel_wager_payment' => '决斗押金支付',
        'duel_wager_refund' => '决斗押金退款',
        'ess_escrow_transfer' => 'ESS托管转移',
        'external_trade_delivery' => '外部交易交付',
        'external_trade_freeze' => '外部交易冻结',
        'external_trade_thaw' => '外部交易解冻',
        'factory_slot_rental_fee' => '工厂槽位租金',
        'flux_payout' => 'Flux支付',
        'flux_tax' => 'Flux税',
        'flux_ticket_repayment' => 'Flux票据偿还',
        'flux_ticket_sale' => 'Flux票据销售',
        'gm_cash_transfer' => 'GM现金转移',
        'industry_job_tax' => '工业任务税',
        'infrastructure_hub_maintenance' => '基建中心维护费',
        'inheritance' => '继承',
        'insurance' => '保险',
        'item_trader_payment' => '物品交易支付',
        'jump_clone_activation_fee' => '跳跃克隆激活费',
        'jump_clone_installation_fee' => '跳跃克隆安装费',
        'kill_right_fee' => '击杀权费',
        'lp_store' => 'LP商店',
        'manufacturing' => '生产',
        'market_escrow' => '市场托管',
        'market_fine_paid' => '市场罚款支付',
        'market_provider_tax' => '市场提供商税',
        'market_transaction' => '市场交易',
        'medal_creation' => '勋章创建',
        'medal_issued' => '勋章颁发',
        'milestone_reward_payment' => '里程碑奖励支付',
        'mission_completion' => '任务完成',
        'mission_cost' => '任务费用',
        'mission_expiration' => '任务过期',
        'mission_reward' => '任务奖励',
        'office_rental_fee' => '办公室租金',
        'operation_bonus' => '行动奖励',
        'opportunity_reward' => '机会奖励',
        'planetary_construction' => '行星建设',
        'planetary_export_tax' => '行星出口税',
        'planetary_import_tax' => '行星进口税',
        'player_donation' => '玩家捐赠',
        'player_trading' => '玩家交易',
        'project_discovery_reward' => '探索项目奖励',
        'project_discovery_tax' => '探索项目税',
        'reaction' => '反应',
        'redeemed_isk_token' => '兑换ISK代币',
        'release_of_impounded_property' => '扣押财产释放',
        'repair_bill' => '维修费',
        'reprocessing_tax' => '再处理税',
        'researching_material_productivity' => '材料效率研究',
        'researching_technology' => '技术研究',
        'researching_time_productivity' => '时间效率研究',
        'resource_wars_reward' => '资源战争奖励',
        'reverse_engineering' => '逆向工程',
        'season_challenge_reward' => '季节挑战奖励',
        'security_processing_fee' => '安全处理费',
        'shares' => '股份',
        'skill_purchase' => '技能购买',
        'sovereignity_bill' => '主权费',
        'store_purchase' => '商店购买',
        'store_purchase_refund' => '商店购买退款',
        'structure_gate_jump' => '建筑星门跳跃',
        'transaction_tax' => '交易税',
        'upkeep_adjustment_fee' => '维护调整费',
        'war_ally_contract' => '战争同盟合同',
        'war_fee' => '战争费',
        'war_fee_surrender' => '战争投降费',
    ];

    /**
     * 获取钱包余额
     */
    public function balance(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $balance = Cache::remember("wallet_balance_{$characterId}", 60, function () use ($baseUrl, $characterId, $token) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/wallet/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return 0;
            });

            return response()->json(['balance' => $balance]);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取钱包余额失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取钱包流水
     */
    public function journal(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');
        $page = $request->input('page', 1);

        try {
            $cacheKey = "wallet_journal_{$characterId}_page_{$page}";
            $journal = Cache::remember($cacheKey, 300, function () use ($baseUrl, $characterId, $token, $page) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/wallet/journal/", [
                        'datasource' => 'serenity',
                        'page' => $page
                    ]);
                
                if ($response->ok()) {
                    return [
                        'data' => $response->json(),
                        'total_pages' => (int) $response->header('X-Pages', 1)
                    ];
                }
                return ['data' => [], 'total_pages' => 1];
            });

            // 添加中文类型名称
            foreach ($journal['data'] as &$entry) {
                $refType = $entry['ref_type'] ?? '';
                $entry['ref_type_name'] = self::JOURNAL_REF_TYPES[$refType] ?? $refType;
            }

            return response()->json($journal);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取钱包流水失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取钱包交易记录
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $transactions = Cache::remember("wallet_transactions_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/wallet/transactions/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return [];
            });

            if (empty($transactions)) {
                return response()->json([]);
            }

            // 获取物品名称
            $typeIds = array_unique(array_column($transactions, 'type_id'));
            $typeNames = EveHelper::getNamesByIds($typeIds, 'item');

            // 添加物品名称
            foreach ($transactions as &$tx) {
                $typeId = $tx['type_id'] ?? 0;
                $tx['type_name'] = $typeNames[$typeId] ?? "物品 #{$typeId}";
            }

            return response()->json($transactions);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取交易记录失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取LP点数
     */
    public function loyalty(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $loyalty = Cache::remember("wallet_loyalty_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/loyalty/points/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return [];
            });

            if (empty($loyalty)) {
                return response()->json([]);
            }

            // 获取军团名称
            $corpIds = array_column($loyalty, 'corporation_id');
            $names = $this->getCorpNames($corpIds);

            // 添加军团名称并按LP点数排序
            foreach ($loyalty as &$item) {
                $corpId = $item['corporation_id'] ?? 0;
                $item['corporation_name'] = $names[$corpId] ?? "军团 #{$corpId}";
            }

            // 按LP点数排序（高到低）
            usort($loyalty, fn($a, $b) => ($b['loyalty_points'] ?? 0) <=> ($a['loyalty_points'] ?? 0));

            return response()->json($loyalty);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取LP点数失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 批量获取军团名称
     */
    private function getCorpNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $baseUrl = config('esi.base_url');
        $names = [];
        $uncachedIds = [];

        foreach ($ids as $id) {
            $cached = Cache::get("eve_corp_name_{$id}");
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
                        Cache::put("eve_corp_name_{$id}", $name, 86400);
                    }
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        return $names;
    }
}
