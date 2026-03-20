<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NotificationDataController extends Controller
{
    /**
     * 通知类型中文映射
     */
    private const NOTIFICATION_TYPES = [
        'AllWarDeclaredMsg' => '联盟宣战',
        'AllWarInvalidatedMsg' => '联盟战争无效',
        'AllWarRetractedMsg' => '联盟撤回战争',
        'AllWarSurrenderMsg' => '联盟投降',
        'BountyClaimMsg' => '赏金领取',
        'BountyESSShared' => '赏金ESS共享',
        'BountyESSTaken' => '赏金ESS被取走',
        'BountyPlacedAlliance' => '联盟被悬赏',
        'BountyPlacedChar' => '角色被悬赏',
        'BountyPlacedCorp' => '军团被悬赏',
        'CharAppAcceptMsg' => '申请已接受',
        'CharAppRejectMsg' => '申请被拒绝',
        'CharAppWithdrawMsg' => '申请已撤回',
        'CharLeftCorpMsg' => '成员离开军团',
        'CloneActivationMsg1' => '克隆体激活',
        'CloneActivationMsg2' => '克隆体激活',
        'CloneMovedMsg' => '克隆体已移动',
        'CloneRevokedMsg1' => '克隆体已撤销',
        'CloneRevokedMsg2' => '克隆体已撤销',
        'CorpAllBillMsg' => '联盟账单',
        'CorpAppAcceptMsg' => '军团申请已接受',
        'CorpAppInvitedMsg' => '军团邀请',
        'CorpAppNewMsg' => '新军团申请',
        'CorpAppRejectMsg' => '军团申请被拒绝',
        'CorpDividendMsg' => '军团分红',
        'CorpKicked' => '被踢出军团',
        'CorpNewCEOMsg' => '新CEO任命',
        'CorpTaxChangeMsg' => '军团税率变更',
        'CorpVoteCEORevokedMsg' => 'CEO投票撤销',
        'CorpVoteMsg' => '军团投票',
        'CorpWarDeclaredMsg' => '军团宣战',
        'CorpWarFightingLegalMsg' => '战争合法',
        'CorpWarInvalidatedMsg' => '战争无效',
        'CorpWarRetractedMsg' => '撤回战争',
        'CorpWarSurrenderMsg' => '军团投降',
        'FWAllianceKickMsg' => '联盟踢出势力战争',
        'FWAllianceWarningMsg' => '势力战争警告',
        'FWCharKickMsg' => '角色踢出势力战争',
        'FWCharWarningMsg' => '角色势力战争警告',
        'FWCorpJoinMsg' => '军团加入势力战争',
        'FWCorpKickMsg' => '军团踢出势力战争',
        'FWCorpLeaveMsg' => '军团离开势力战争',
        'FWCorpWarningMsg' => '军团势力战争警告',
        'InsuranceExpirationMsg' => '保险到期',
        'InsuranceFirstShipMsg' => '首艘舰船保险',
        'InsuranceInvalidatedMsg' => '保险无效',
        'InsuranceIssuedMsg' => '保险签发',
        'InsurancePayoutMsg' => '保险赔付',
        'JumpCloneDeletedMsg1' => '跳跃克隆已删除',
        'JumpCloneDeletedMsg2' => '跳跃克隆已删除',
        'KillReportFinalBlow' => '最后一击报告',
        'KillReportVictim' => '被击杀报告',
        'KillRightAvailable' => '击杀权可用',
        'KillRightAvailableOpen' => '开放击杀权可用',
        'KillRightEarned' => '获得击杀权',
        'KillRightUnavailable' => '击杀权不可用',
        'KillRightUnavailableOpen' => '开放击杀权不可用',
        'KillRightUsed' => '击杀权已使用',
        'LocateCharMsg' => '定位角色',
        'MadeWarMutual' => '战争变为互相',
        'MercOfferedNegotiationMsg' => '雇佣兵谈判',
        'MissionOfferExpirationMsg' => '任务过期',
        'MoonminingAutomaticFracture' => '月矿自动开采',
        'MoonminingExtractionCancelled' => '月矿开采取消',
        'MoonminingExtractionFinished' => '月矿开采完成',
        'MoonminingExtractionStarted' => '月矿开采开始',
        'MoonminingLaserFired' => '月矿激光启动',
        'NPCStandingsGained' => 'NPC声望提升',
        'NPCStandingsLost' => 'NPC声望下降',
        'OfferToAllyRetracted' => '同盟邀请撤回',
        'OfferedSurrender' => '提议投降',
        'OfferedToAlly' => '提议同盟',
        'OldLscMessages' => '旧频道消息',
        'OperationFinished' => '行动完成',
        'OrbitalAttacked' => '轨道设施受攻击',
        'OrbitalReinforced' => '轨道设施增援',
        'OwnershipTransferred' => '所有权转移',
        'ReimbursementMsg' => '报销',
        'ResearchMissionAvailableMsg' => '研究任务可用',
        'RetractsWar' => '撤回战争',
        'SeasonalChallengeCompleted' => '季节挑战完成',
        'SovAllClaimAquiredMsg' => '主权占领',
        'SovAllClaimLostMsg' => '主权丢失',
        'SovCommandNodeEventStarted' => '主权指挥节点事件开始',
        'SovCorpBillLateMsg' => '主权账单逾期',
        'SovCorpClaimFailMsg' => '主权占领失败',
        'SovDisruptorMsg' => '主权干扰',
        'SovStationEnteredFreeport' => '空间站进入自由港模式',
        'SovStructureDestroyed' => '主权设施被摧毁',
        'SovStructureReinforced' => '主权设施增援',
        'SovStructureSelfDestructCancel' => '取消自毁',
        'SovStructureSelfDestructFinished' => '自毁完成',
        'SovStructureSelfDestructRequested' => '请求自毁',
        'StationAggressionMsg1' => '空间站攻击',
        'StationAggressionMsg2' => '空间站攻击',
        'StationConquerMsg' => '空间站占领',
        'StationServiceDisabled' => '空间站服务禁用',
        'StationServiceEnabled' => '空间站服务启用',
        'StationStateChangeMsg' => '空间站状态变更',
        'StoryLineMissionAvailableMsg' => '剧情任务可用',
        'StructureAnchoring' => '建筑锚定',
        'StructureCourierContractChanged' => '建筑快递合同变更',
        'StructureDestroyed' => '建筑被摧毁',
        'StructureFuelAlert' => '建筑燃料警告',
        'StructureItemsDelivered' => '建筑物品交付',
        'StructureItemsMovedToSafety' => '物品转移到安全处',
        'StructureLostArmor' => '建筑装甲损失',
        'StructureLostShields' => '建筑护盾损失',
        'StructureOnline' => '建筑上线',
        'StructureServicesOffline' => '建筑服务下线',
        'StructureUnanchoring' => '建筑解锚',
        'StructureUnderAttack' => '建筑受攻击',
        'StructureWentHighPower' => '建筑高功率',
        'StructureWentLowPower' => '建筑低功率',
        'TowerAlertMsg' => 'POS警报',
        'TowerResourceAlertMsg' => 'POS资源警报',
        'TransactionReversalMsg' => '交易撤销',
        'TutorialMsg' => '教程消息',
        'WarAdopted' => '战争被接管',
        'WarAllyInherited' => '继承战争同盟',
        'WarAllyOfferDeclinedMsg' => '同盟邀请被拒绝',
        'WarConcordInvalidates' => '统合部宣布战争无效',
        'WarDeclared' => '宣战',
        'WarHQRemovedFromSpace' => '战争总部被摧毁',
        'WarInherited' => '继承战争',
        'WarInvalid' => '战争无效',
        'WarRetracted' => '战争撤回',
        'WarRetractedByConcord' => '统合部撤回战争',
        'WarSurrenderDeclinedMsg' => '投降被拒绝',
        'WarSurrenderOfferMsg' => '投降提议',
    ];

    /**
     * 获取角色提醒数据
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = config('esi.base_url');

        try {
            $notifications = Cache::remember("notifications_{$characterId}", 300, function () use ($baseUrl, $characterId, $token) {
                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}characters/{$characterId}/notifications/", [
                        'datasource' => 'serenity'
                    ]);
                
                if ($response->ok()) {
                    return $response->json();
                }
                return [];
            });

            if (empty($notifications)) {
                return response()->json([]);
            }

            // 按时间倒序排列
            usort($notifications, function ($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '');
                $timeB = strtotime($b['timestamp'] ?? '');
                return $timeB - $timeA;
            });

            // 构建返回数据
            $result = [];
            foreach ($notifications as $notification) {
                $type = $notification['type'] ?? '';
                $result[] = [
                    'notification_id' => $notification['notification_id'] ?? 0,
                    'type' => $type,
                    'type_name' => self::NOTIFICATION_TYPES[$type] ?? $type,
                    'sender_id' => $notification['sender_id'] ?? 0,
                    'sender_type' => $notification['sender_type'] ?? '',
                    'timestamp' => $notification['timestamp'] ?? '',
                    'is_read' => $notification['is_read'] ?? false,
                    'text' => $notification['text'] ?? ''
                ];
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json(['error' => '获取提醒数据失败: ' . $e->getMessage()], 500);
        }
    }
}
