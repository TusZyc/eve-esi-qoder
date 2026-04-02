<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\TokenService;

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
        'InsurancePayoutMsg' => '保险赔付',
        'InsuranceFirstShipMsg' => '首舰保险',
        'InsuranceInvalidatedMsg' => '保险失效',
        'InsuranceExpirationMsg' => '保险到期',
        'InsuranceIssuedMsg' => '保险生效',
    ];

    /**
     * 需要通过 ESI /universe/names/ 解析的字段（角色、军团、联盟）
     */
    private const NAME_ID_FIELDS = [
        'charID', 'victimID', 'aggressorID', 'corpID', 'aggressorCorpID',
        'defenderCorpID', 'allianceID', 'aggressorAllianceID', 'defenderAllianceID',
        'againstID', 'declaredByID', 'entityID', 'senderCharID', 'finalBlowAttacker',
        'podKillerID', 'attackerID', 'attackerCorpID', 'attackerAllianceID',
        'ownerCorpID', 'oldOwnerCorpID', 'newOwnerCorpID',
    ];

    /**
     * 星系 ID 字段
     */
    private const SYSTEM_ID_FIELDS = ['solarSystemID', 'systemID'];

    /**
     * 物品类型 ID 字段
     */
    private const TYPE_ID_FIELDS = ['typeID', 'shipTypeID', 'structureTypeID'];

    /**
     * 本地缓存的星系数据
     */
    private $systemsCache = null;

    /**
     * 本地缓存的物品数据
     */
    private $itemsCache = null;

    /**
     * 获取角色提醒数据
     */
    public function index(Request $request)
    {
        Log::info('🔔 [Notifications] 开始获取通知数据');
        
        $user = $request->user();
        if (!$user) {
            Log::error('🔔 [Notifications] 用户未登录');
            return response()->json(['error' => '用户未登录'], 401);
        }
        
        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');
        
        Log::info('🔔 [Notifications] 用户信息', [
            'user_id' => $user->id,
            'character_id' => $characterId,
            'token_length' => strlen($token)
        ]);

        try {
            $notifications = Cache::remember("notifications_{$characterId}", 300, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/notifications/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed');
                }
                return $response->json();
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

            // 收集所有需要解析的 ID
            $nameIds = [];
            $systemIds = [];
            $typeIds = [];
            $parsedTexts = [];

            // sender_id 也需要解析（仅限可解析的 sender_type）
            foreach ($notifications as $notification) {
                $senderId = $notification['sender_id'] ?? 0;
                $senderType = $notification['sender_type'] ?? '';
                if ($senderId > 0 && in_array($senderType, ['character', 'corporation', 'alliance', 'faction'])) {
                    $nameIds[] = $senderId;
                }
            }

            // 解析每个通知的 text 字段，收集 ID
            foreach ($notifications as $idx => $notification) {
                $text = $notification['text'] ?? '';
                $parsed = $this->parseNotificationText($text);
                $parsedTexts[$idx] = $parsed;

                // 收集需要解析的 ID
                foreach ($parsed as $key => $value) {
                    if (!is_numeric($value) || $value <= 0) continue;
                    
                    $intValue = (int) $value;
                    
                    if (in_array($key, self::NAME_ID_FIELDS)) {
                        $nameIds[] = $intValue;
                    } elseif (in_array($key, self::SYSTEM_ID_FIELDS)) {
                        $systemIds[] = $intValue;
                    } elseif (in_array($key, self::TYPE_ID_FIELDS)) {
                        $typeIds[] = $intValue;
                    }
                }
            }

            // 批量解析名称
            $resolvedNames = $this->resolveNames(array_unique($nameIds), $baseUrl);
            $resolvedSystems = $this->resolveSystemNames(array_unique($systemIds));
            $resolvedTypes = $this->resolveTypeNames(array_unique($typeIds), $baseUrl);

            // 合并所有解析结果
            $allResolved = array_merge($resolvedNames, $resolvedSystems, $resolvedTypes);

            // 构建返回数据
            $result = [];
            foreach ($notifications as $idx => $notification) {
                $type = $notification['type'] ?? '';
                $senderId = $notification['sender_id'] ?? 0;
                
                $result[] = [
                    'notification_id' => $notification['notification_id'] ?? 0,
                    'type' => $type,
                    'type_name' => self::NOTIFICATION_TYPES[$type] ?? $type,
                    'sender_id' => $senderId,
                    'sender_type' => $notification['sender_type'] ?? '',
                    'sender_name' => $resolvedNames[$senderId] ?? null,
                    'timestamp' => $notification['timestamp'] ?? '',
                    'is_read' => $notification['is_read'] ?? false,
                    'text' => $notification['text'] ?? '',
                    'parsed_text' => $parsedTexts[$idx] ?? [],
                    'resolved_names' => $allResolved,
                ];
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('🔔 [Notifications] 获取提醒数据失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => '获取提醒数据失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 轻量级通知摘要（仅返回类型名+时间+未读状态，用于下拉框）
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $notifications = Cache::remember("notifications_{$characterId}", 300, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/notifications/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed');
                }
                return $response->json();
            });

            if (empty($notifications)) {
                return response()->json(['unread' => 0, 'items' => []]);
            }

            usort($notifications, function ($a, $b) {
                return strtotime($b['timestamp'] ?? '') - strtotime($a['timestamp'] ?? '');
            });

            $unread = count(array_filter($notifications, fn($n) => !($n['is_read'] ?? false)));
            $recent = array_slice($notifications, 0, 10);

            $items = array_map(function ($n) {
                $type = $n['type'] ?? '';
                return [
                    'notification_id' => $n['notification_id'] ?? 0,
                    'type_name' => self::NOTIFICATION_TYPES[$type] ?? $type,
                    'timestamp' => $n['timestamp'] ?? '',
                    'is_read' => $n['is_read'] ?? false,
                ];
            }, $recent);

            return response()->json(['unread' => $unread, 'items' => $items]);
        } catch (\Exception $e) {
            Log::error('🔔 [Notifications] 摘要获取失败', ['error' => $e->getMessage()]);
            return response()->json(['error' => '获取失败'], 500);
        }
    }

    /**
     * 解析通知 text 字段（YAML 格式）
     */
    private function parseNotificationText(string $text): array
    {
        if (empty($text)) return [];

        $result = [];
        $anchors = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;

            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            // 处理 YAML anchor: &id001 12345
            if (preg_match('/^&(\S+)\s+(.+)$/', $value, $m)) {
                $anchors[$m[1]] = $m[2];
                $value = $m[2];
            }
            // 处理 YAML reference: *id001
            if (preg_match('/^\*(\S+)$/', $value, $m)) {
                $value = $anchors[$m[1]] ?? $value;
            }

            // 去除可能的引号
            $value = trim($value, '"\'');

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * 批量解析角色、军团、联盟名称（使用 ESI /universe/names/）
     */
    private function resolveNames(array $ids, string $baseUrl): array
    {
        if (empty($ids)) return [];

        // 过滤有效 ID（ESI 限制 int32 范围，排除超大的建筑 ID 等）
        $ids = array_filter($ids, fn($id) => is_numeric($id) && $id > 0 && $id < 2147483647);
        $ids = array_values(array_unique($ids));
        if (empty($ids)) return [];

        $cacheKey = 'esi_names_' . md5(implode(',', $ids));

        try {
            return Cache::remember($cacheKey, 3600, function () use ($ids, $baseUrl) {
                $map = [];

                // ESI 一次最多 1000 个 ID，分批处理
                $chunks = array_chunk($ids, 1000);

                foreach ($chunks as $chunk) {
                    $resolved = $this->esiResolveNamesBatch($chunk, $baseUrl);
                    $map = array_replace($map, $resolved);
                }

                return $map;
            });
        } catch (\Exception $e) {
            Log::warning('ESI names 缓存操作失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 批量请求 ESI universe/names，失败时自动拆分重试
     */
    private function esiResolveNamesBatch(array $ids, string $baseUrl, int $depth = 0): array
    {
        if (empty($ids) || $depth > 3) return [];

        try {
            // universe/names 是公共端点，不需要认证
            $response = Http::timeout(15)
                ->post("{$baseUrl}/universe/names/?datasource=serenity", array_values($ids));

            if ($response->successful()) {
                $map = [];
                foreach ($response->json() as $item) {
                    if (isset($item['id'], $item['name'])) {
                        $map[$item['id']] = $item['name'];
                    }
                }
                return $map;
            }

            // 404 = 包含无效 ID，拆分后重试
            if ($response->status() === 404 && count($ids) > 1) {
                $mid = (int) ceil(count($ids) / 2);
                $left = array_slice($ids, 0, $mid);
                $right = array_slice($ids, $mid);
                return array_replace(
                    $this->esiResolveNamesBatch($left, $baseUrl, $depth + 1),
                    $this->esiResolveNamesBatch($right, $baseUrl, $depth + 1)
                );
            }

            Log::warning('ESI names 请求失败', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
                'ids_count' => count($ids)
            ]);
        } catch (\Exception $e) {
            Log::warning('ESI names 批次解析异常: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * 解析星系名称（使用本地 eve_systems_full.json）
     */
    private function resolveSystemNames(array $systemIds): array
    {
        if (empty($systemIds)) return [];

        $map = [];
        $systems = $this->getSystemsData();
        
        if (empty($systems)) return $map;

        foreach ($systemIds as $id) {
            $strId = (string) $id;
            if (isset($systems[$strId]) && isset($systems[$strId]['name'])) {
                $map[$id] = $systems[$strId]['name'];
            }
        }

        return $map;
    }

    /**
     * 解析物品类型名称（优先使用本地 items.json，未找到则调用 ESI）
     */
    private function resolveTypeNames(array $typeIds, string $baseUrl): array
    {
        if (empty($typeIds)) return [];

        $map = [];
        $items = $this->getItemsData();
        $needEsi = [];

        // 先从本地数据查找
        foreach ($typeIds as $id) {
            $found = false;
            foreach ($items as $item) {
                if (isset($item['id']) && $item['id'] == $id) {
                    $map[$id] = $item['name'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $needEsi[] = $id;
            }
        }

        // 本地未找到的，从 ESI 获取（universe/types 是公共端点，不需要认证）
        foreach ($needEsi as $typeId) {
            $cacheKey = "esi_type_{$typeId}";
            try {
                $name = Cache::remember($cacheKey, 86400, function () use ($typeId, $baseUrl) {
                    $response = Http::timeout(10)
                        ->get("{$baseUrl}/universe/types/{$typeId}/", [
                            'datasource' => 'serenity',
                            'language' => 'zh'
                        ]);

                    if (!$response->successful()) {
                        Log::warning("ESI type 请求失败", [
                            'type_id' => $typeId,
                            'status' => $response->status(),
                            'body' => mb_substr($response->body(), 0, 200)
                        ]);
                        return null;
                    }

                    $data = $response->json();
                    return $data['name'] ?? null;
                });

                if ($name) {
                    $map[$typeId] = $name;
                }
            } catch (\Exception $e) {
                Log::warning("ESI type {$typeId} 解析异常: " . $e->getMessage());
                // 跳过失败的类型，继续处理其他
            }
        }

        return $map;
    }

    /**
     * 获取本地星系数据
     */
    private function getSystemsData(): array
    {
        if ($this->systemsCache !== null) {
            return $this->systemsCache;
        }

        $path = base_path('data/eve_systems_full.json');
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $this->systemsCache = json_decode($content, true) ?? [];
        
        return $this->systemsCache;
    }

    /**
     * 获取本地物品数据
     */
    private function getItemsData(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $path = base_path('data/items.json');
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $this->itemsCache = json_decode($content, true) ?? [];
        
        return $this->itemsCache;
    }
}
