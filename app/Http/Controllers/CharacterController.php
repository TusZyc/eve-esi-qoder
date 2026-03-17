<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Helpers\EveHelper;
use App\Services\CharacterDataService;
use App\Services\CacheKeyService;
use App\Services\ApiErrorHandler;
use App\Exceptions\EveApiException;

class CharacterController extends Controller
{
    /**
     * 角色管理页 — 直接显示角色全部信息
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isLoggedIn = $user && $user->eve_character_id !== null;

        $character = null;
        if ($isLoggedIn) {
            $response = Http::timeout(10)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/');

            if ($response->ok()) {
                $character = $response->json();

                // 创建日期转北京时间
                if (!empty($character['birthday'])) {
                    $character['birthday_beijing'] = Carbon::parse($character['birthday'])
                        ->timezone('Asia/Shanghai')
                        ->format('Y-m-d H:i:s');
                }

                // 军团和联盟ID转名称
                $idsToResolve = [];
                if (!empty($character['corporation_id'])) {
                    $idsToResolve[] = $character['corporation_id'];
                }
                if (!empty($character['alliance_id'])) {
                    $idsToResolve[] = $character['alliance_id'];
                }
                if (!empty($idsToResolve)) {
                    $resolvedNames = $this->resolveNames($idsToResolve);
                    if (!empty($character['corporation_id'])) {
                        $character['corporation_name'] = $resolvedNames[$character['corporation_id']] ?? null;
                    }
                    if (!empty($character['alliance_id'])) {
                        $character['alliance_name'] = $resolvedNames[$character['alliance_id']] ?? null;
                    }
                }

                // 角色描述解码
                if (!empty($character['description'])) {
                    $character['description_html'] = $this->decodeEveDescription($character['description']);
                }
            }
        }

        return view('characters.index', compact('user', 'character', 'isLoggedIn'));
    }

    /**
     * 角色详情 — 重定向到index
     */
    public function show(Request $request, $characterId)
    {
        return redirect()->route('characters.index');
    }

    /**
     * 角色属性 (API)
     */
    public function attributes(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('esi.base_url');
        $cacheKey = "char:attributes:{$user->eve_character_id}";

        try {
            $data = Cache::remember($cacheKey, CacheKeyService::TTL_SHORT, function () use ($user, $baseUrl) {
                $response = Http::withToken($user->access_token)->timeout(10)
                    ->get($baseUrl . 'characters/' . $user->eve_character_id . '/attributes/');

                if ($response->failed()) {
                    return ['_error' => true, '_status' => $response->status()];
                }

                return $response->json();
            });

            if (isset($data['_error'])) {
                Cache::forget($cacheKey);
                $status = $data['_status'];
                if ($status === 403) {
                    return response()->json(['success' => false, 'message' => '权限不足，请重新授权以获取属性数据'], 403);
                }
                return response()->json(['success' => false, 'message' => '获取属性失败'], $status);
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '请求超时'], 500);
        }
    }

    /**
     * 当前植入体 (API)
     */
    public function implants(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('esi.base_url');
        $cacheKey = "char:implants:{$user->eve_character_id}";

        try {
            $data = Cache::remember($cacheKey, CacheKeyService::TTL_SHORT, function () use ($user, $baseUrl) {
                $response = Http::withToken($user->access_token)->timeout(10)
                    ->get($baseUrl . 'characters/' . $user->eve_character_id . '/implants/');

                if ($response->failed()) {
                    return ['_error' => true, '_status' => $response->status()];
                }

                $typeIds = $response->json() ?: [];
                $implants = [];
                if (!empty($typeIds)) {
                    $names = EveHelper::getNamesByIds($typeIds, 'item');
                    foreach ($typeIds as $typeId) {
                        $implants[] = [
                            'type_id' => $typeId,
                            'name' => $names[$typeId] ?? "Unknown #{$typeId}",
                        ];
                    }
                    usort($implants, fn($a, $b) => strcmp($a['name'], $b['name']));
                }

                return $implants;
            });

            if (isset($data['_error'])) {
                Cache::forget($cacheKey);
                $status = $data['_status'];
                if ($status === 403) {
                    return response()->json(['success' => false, 'message' => '权限不足，请重新授权以获取植入体数据'], 403);
                }
                return response()->json(['success' => false, 'message' => '获取植入体失败'], $status);
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '请求超时'], 500);
        }
    }

    /**
     * 克隆体信息 (API)
     */
    public function clones(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('esi.base_url');

        try {
            $response = Http::withToken($user->access_token)->timeout(10)
                ->get($baseUrl . 'characters/' . $user->eve_character_id . '/clones/');

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 403) {
                    return response()->json(['success' => false, 'message' => '权限不足，请重新授权以获取克隆体数据'], 403);
                }
                return response()->json(['success' => false, 'message' => '获取克隆体失败'], $status);
            }

            $data = $response->json();

            // 解析基地空间站名称
            $homeLocation = $data['home_location'] ?? null;
            if ($homeLocation && !empty($homeLocation['location_id'])) {
                $homeLocation['location_name'] = $this->resolveLocationName(
                    $homeLocation['location_id'],
                    $homeLocation['location_type'] ?? 'station',
                    $user
                );
            }

            // 解析跳跃克隆
            $jumpClones = $data['jump_clones'] ?? [];
            $allImplantIds = [];
            $locationIds = [];
            foreach ($jumpClones as $clone) {
                if (!empty($clone['implants'])) {
                    $allImplantIds = array_merge($allImplantIds, $clone['implants']);
                }
                if (!empty($clone['location_id'])) {
                    $locationIds[] = $clone['location_id'];
                }
            }

            // 批量解析植入体名称
            $allImplantIds = array_unique($allImplantIds);
            $implantNames = !empty($allImplantIds) ? EveHelper::getNamesByIds($allImplantIds, 'item') : [];

            // 逐个解析位置名称（使用 stations/structures 端点获取中文名）
            $locationNames = [];
            foreach (array_unique($locationIds) as $locId) {
                // 找到该 location 对应的 type
                $locType = 'station';
                foreach ($jumpClones as $clone) {
                    if (($clone['location_id'] ?? 0) == $locId) {
                        $locType = $clone['location_type'] ?? 'station';
                        break;
                    }
                }
                $locationNames[$locId] = $this->resolveLocationName($locId, $locType, $user);
            }

            // 组装跳跃克隆数据
            $clones = [];
            foreach ($jumpClones as $clone) {
                $cloneImplants = [];
                foreach ($clone['implants'] ?? [] as $typeId) {
                    $cloneImplants[] = [
                        'type_id' => $typeId,
                        'name' => $implantNames[$typeId] ?? "Unknown #{$typeId}",
                    ];
                }
                usort($cloneImplants, fn($a, $b) => strcmp($a['name'], $b['name']));

                $locId = $clone['location_id'] ?? 0;
                $clones[] = [
                    'jump_clone_id' => $clone['jump_clone_id'] ?? 0,
                    'location_id' => $locId,
                    'location_name' => $locationNames[$locId] ?? $this->resolveLocationName($locId, $clone['location_type'] ?? 'station', $user),
                    'location_type' => $clone['location_type'] ?? 'station',
                    'implants' => $cloneImplants,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'home_location' => $homeLocation,
                    'jump_clones' => $clones,
                    'last_clone_jump_date' => $data['last_clone_jump_date'] ?? null,
                    'last_station_change_date' => $data['last_station_change_date'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '请求超时'], 500);
        }
    }

    /**
     * 雇佣历史 (API, 公开端点)
     */
    public function corporationHistory(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('esi.base_url');

        try {
            $response = Http::timeout(10)
                ->get($baseUrl . 'characters/' . $user->eve_character_id . '/corporationhistory/');

            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => '获取雇佣历史失败'], $response->status());
            }

            $history = $response->json() ?: [];

            // 批量解析军团名称
            $corpIds = array_unique(array_column($history, 'corporation_id'));
            $corpNames = !empty($corpIds) ? $this->resolveNames($corpIds) : [];

            // ESI 返回按 record_id 降序（最新在前），start_date 是加入时间
            // 每条的结束时间 = 上一条(更新)的 start_date，最新一条 = 至今
            $now = Carbon::now('Asia/Shanghai');
            $result = [];
            for ($i = 0; $i < count($history); $i++) {
                $item = $history[$i];
                $corpId = $item['corporation_id'] ?? 0;

                $startCarbon = !empty($item['start_date']) ? Carbon::parse($item['start_date'])->timezone('Asia/Shanghai') : null;
                $startDate = $startCarbon ? $startCarbon->format('Y-m-d') : null;

                if ($i === 0) {
                    $endDate = '至今';
                    $days = $startCarbon ? (int) $startCarbon->diffInDays($now) : 0;
                } else {
                    $prevStart = !empty($history[$i - 1]['start_date']) ? Carbon::parse($history[$i - 1]['start_date'])->timezone('Asia/Shanghai') : null;
                    $endDate = $prevStart ? $prevStart->format('Y-m-d') : '未知';
                    $days = ($startCarbon && $prevStart) ? (int) $startCarbon->diffInDays($prevStart) : 0;
                }

                $result[] = [
                    'corporation_id' => $corpId,
                    'corporation_name' => $corpNames[$corpId] ?? "Unknown #{$corpId}",
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => $days,
                    'record_id' => $item['record_id'] ?? 0,
                    'is_deleted' => $item['is_deleted'] ?? false,
                ];
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '请求超时'], 500);
        }
    }

    /**
     * 刷新角色数据
     */
    public function refresh(Request $request, $characterId)
    {
        return redirect()->back()
            ->with('success', '数据已刷新');
    }

    /**
     * 解绑角色
     */
    public function destroy(Request $request, $characterId)
    {
        $user = $request->user();

        if ($user->eve_character_id == $characterId) {
            return redirect()->back()
                ->with('error', '不能解绑当前登录的角色');
        }

        return redirect()->route('characters.index')
            ->with('success', '角色已解绑');
    }
    
    /**
     * 获取角色全部数据 - 聚合 API 端点
     * 并行获取属性、植入体、克隆体、雇佣历史
     */
    public function allData(Request $request)
    {
        try {
            $user = ApiErrorHandler::requireAuth($request);
            // 使用 CharacterDataService 并行获取数据
            $data = CharacterDataService::getAllCharacterData(
                $user->eve_character_id,
                $user->access_token
            );
            
            // 处理植入体名称
            $implants = [];
            if (!empty($data['implants'])) {
                $typeIds = $data['implants'];
                $names = EveHelper::getNamesByIds($typeIds, 'item');
                foreach ($typeIds as $typeId) {
                    $implants[] = [
                        'type_id' => $typeId,
                        'name' => $names[$typeId] ?? "Unknown #{$typeId}",
                    ];
                }
                usort($implants, fn($a, $b) => strcmp($a['name'], $b['name']));
            }
            
            // 处理雇佣历史
            $history = [];
            if (!empty($data['history'])) {
                $corpIds = array_unique(array_column($data['history'], 'corporation_id'));
                $corpNames = !empty($corpIds) ? $this->resolveNames($corpIds) : [];
                $now = Carbon::now('Asia/Shanghai');
                
                for ($i = 0; $i < count($data['history']); $i++) {
                    $item = $data['history'][$i];
                    $corpId = $item['corporation_id'] ?? 0;
                    $startCarbon = !empty($item['start_date']) ? Carbon::parse($item['start_date'])->timezone('Asia/Shanghai') : null;
                    
                    if ($i === 0) {
                        $endDate = '至今';
                        $days = $startCarbon ? (int) $startCarbon->diffInDays($now) : 0;
                    } else {
                        $prevStart = !empty($data['history'][$i - 1]['start_date']) ? Carbon::parse($data['history'][$i - 1]['start_date'])->timezone('Asia/Shanghai') : null;
                        $endDate = $prevStart ? $prevStart->format('Y-m-d') : '未知';
                        $days = ($startCarbon && $prevStart) ? (int) $startCarbon->diffInDays($prevStart) : 0;
                    }
                    
                    $history[] = [
                        'corporation_id' => $corpId,
                        'corporation_name' => $corpNames[$corpId] ?? "Unknown #{$corpId}",
                        'start_date' => $startCarbon ? $startCarbon->format('Y-m-d') : null,
                        'end_date' => $endDate,
                        'days' => $days,
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'attributes' => $data['attributes'],
                    'implants' => $implants,
                    'clones' => $data['clones'],
                    'corporation_history' => $history,
                ],
            ]);
            
        } catch (EveApiException $e) {
            return $e->toResponse();
        } catch (\Exception $e) {
            return ApiErrorHandler::error('unknown_error', $e->getMessage(), 500);
        }
    }

    /**
     * 技能队列
     */
    public function skillQueue(Request $request)
    {
        $user = $request->user();

        $response = Http::withToken($user->access_token)
            ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skillqueue/');

        if ($response->failed()) {
            return response()->json(['error' => '获取技能队列失败'], 400);
        }

        return response()->json($response->json());
    }

    /**
     * 资产列表
     */
    public function assets(Request $request)
    {
        $user = $request->user();

        $response = Http::withToken($user->access_token)
            ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/assets/');

        if ($response->failed()) {
            return response()->json(['error' => '获取资产失败'], 400);
        }

        return response()->json($response->json());
    }

    /**
     * 钱包余额
     */
    public function wallet(Request $request)
    {
        $user = $request->user();

        $response = Http::withToken($user->access_token)
            ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/wallet/');

        if ($response->failed()) {
            return response()->json(['error' => '获取钱包信息失败'], 400);
        }

        return response()->json($response->json());
    }

    /**
     * 通过 ESI universe/names 接口批量解析ID为名称
     */
    private function resolveNames(array $ids): array
    {
        $names = [];
        $uncached = [];

        foreach ($ids as $id) {
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $uncached[] = $id;
            }
        }

        if (!empty($uncached)) {
            try {
                $response = Http::timeout(10)
                    ->post(config('esi.base_url') . 'universe/names/', array_values($uncached));
                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        $names[$item['id']] = $item['name'];
                        Cache::put("eve_name_{$item['id']}", $item['name'], 86400);
                    }
                }
            } catch (\Exception $e) {
                // fallback: 不影响页面显示
            }
        }

        return $names;
    }

    /**
     * 解析位置名称（空间站或建筑），NPC 空间站逐段翻译为中文
     */
    private function resolveLocationName(int $locationId, string $locationType, $user): string
    {
        $cached = Cache::get("eve_location_name_zh_{$locationId}");
        if ($cached !== null) {
            return $cached;
        }

        $baseUrl = config('esi.base_url');
        $name = "Unknown #{$locationId}";

        try {
            if ($locationType === 'structure' && $user) {
                // 玩家建筑需要token
                $response = Http::withToken($user->access_token)->timeout(10)
                    ->get($baseUrl . "universe/structures/{$locationId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                if ($response->ok()) {
                    $name = $response->json()['name'] ?? $name;
                }
            } else {
                // NPC空间站 — ESI stations 端点不支持中文，需逐段翻译
                $response = Http::timeout(10)
                    ->get($baseUrl . "universe/stations/{$locationId}/", ['datasource' => 'serenity']);
                if ($response->ok()) {
                    $data = $response->json();
                    $name = $data['name'] ?? $name;
                    $systemId = $data['system_id'] ?? 0;
                    $ownerId = $data['owner'] ?? 0;
                    $name = $this->translateStationName($name, $systemId, $ownerId);
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        Cache::put("eve_location_name_zh_{$locationId}", $name, 86400);
        return $name;
    }

    /**
     * 逐段翻译 NPC 空间站英文名为中文（星系名 + 军团名 + 设施类型）
     */
    private function translateStationName(string $name, int $systemId, int $ownerId): string
    {
        $baseUrl = config('esi.base_url');

        // 1) 替换星系名
        if ($systemId > 0) {
            $zhSys = Cache::get("eve_sysname_{$systemId}");
            $enSys = Cache::get("eve_sysname_en_{$systemId}");

            if ($zhSys === null || $enSys === null) {
                try {
                    $responses = Http::pool(function ($pool) use ($baseUrl, $systemId) {
                        $pool->as('zh')->timeout(5)
                            ->get($baseUrl . "universe/systems/{$systemId}/", ['datasource' => 'serenity', 'language' => 'zh']);
                        $pool->as('en')->timeout(5)
                            ->get($baseUrl . "universe/systems/{$systemId}/", ['datasource' => 'serenity', 'language' => 'en']);
                    });
                    if (isset($responses['zh']) && $responses['zh'] instanceof \Illuminate\Http\Client\Response && $responses['zh']->ok()) {
                        $zhSys = $responses['zh']->json()['name'] ?? '';
                        Cache::put("eve_sysname_{$systemId}", $zhSys, 86400);
                    }
                    if (isset($responses['en']) && $responses['en'] instanceof \Illuminate\Http\Client\Response && $responses['en']->ok()) {
                        $enSys = $responses['en']->json()['name'] ?? '';
                        Cache::put("eve_sysname_en_{$systemId}", $enSys, 86400);
                    }
                } catch (\Exception $e) {}
            }

            if ($enSys && $zhSys && str_starts_with($name, $enSys)) {
                $name = $zhSys . substr($name, strlen($enSys));
            }
        }

        // 2) Moon -> 卫星
        $name = preg_replace('/\bMoon\b/', '卫星', $name);

        // 3) 替换军团名
        if ($ownerId > 0) {
            $enCorp = Cache::get("eve_corpname_en_{$ownerId}");
            $zhCorp = Cache::get("eve_name_{$ownerId}");

            if ($enCorp === null) {
                try {
                    $resp = Http::timeout(5)->get($baseUrl . "corporations/{$ownerId}/", ['datasource' => 'serenity']);
                    if ($resp->ok()) {
                        $enCorp = $resp->json()['name'] ?? '';
                        Cache::put("eve_corpname_en_{$ownerId}", $enCorp, 86400);
                    }
                } catch (\Exception $e) {}
            }
            if ($zhCorp === null) {
                try {
                    $resp = Http::timeout(5)->post($baseUrl . 'universe/names/', [$ownerId]);
                    if ($resp->ok()) {
                        $items = $resp->json();
                        if (!empty($items)) {
                            $zhCorp = $items[0]['name'] ?? '';
                            Cache::put("eve_name_{$ownerId}", $zhCorp, 86400);
                        }
                    }
                } catch (\Exception $e) {}
            }

            if ($enCorp && $zhCorp && $enCorp !== $zhCorp) {
                $name = str_replace($enCorp, $zhCorp, $name);
            }
        }

        // 4) 设施类型中英文映射
        $facilityMap = [
            'Assembly Plant' => '组装工厂', 'Refinery' => '精炼厂',
            'Warehouse' => '仓库', 'Storage' => '储藏设施',
            'Factory' => '工厂', 'Trading Post' => '贸易站',
            'Hub' => '集散中心', 'Academy' => '学院',
            'Logistic Support' => '后勤支援', 'Testing Facilities' => '测试设施',
            'Cloning Facility' => '克隆设施', 'Foundry' => '铸造厂',
            'Biotech Research Center' => '生物科技研究中心',
            'Research Center' => '研究中心', 'School' => '学校',
            'Treasury' => '金库', 'Bureau' => '事务局',
            'Tribunal' => '法庭', 'Mining Station' => '采矿站',
            'Accounting' => '会计处', 'Mint' => '铸币厂',
            'Shipyard' => '船坞', 'Military School' => '军事学校',
            'Station' => '空间站', 'Headquarters' => '总部',
            'Law School' => '法学院', 'Plantation' => '种植园',
            'Surveillance' => '监控站', 'Commerce' => '商务站',
            'Food Packaging' => '食品包装厂',
        ];
        foreach ($facilityMap as $en => $zh) {
            if (str_contains($name, $en)) {
                $name = str_replace($en, $zh, $name);
                break;
            }
        }

        return $name;
    }

    /**
     * 解码 EVE 角色描述：处理 Python unicode 字面量、HTML 实体和 EVE 特有标签
     */
    private function decodeEveDescription(string $desc): string
    {
        // 去掉 Python u'...' 包装
        if (preg_match("/^u'(.*)'$/s", $desc, $m)) {
            $desc = $m[1];
        } elseif (preg_match('/^u"(.*)"$/s', $desc, $m)) {
            $desc = $m[1];
        }

        // 解码 \uXXXX unicode 转义 → UTF-8
        $desc = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $desc);

        // 解码 \xNN 作为 Unicode 码点 U+00NN → UTF-8（不是原始字节）
        $desc = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function ($m) {
            return mb_convert_encoding(pack('H*', '00' . $m[1]), 'UTF-8', 'UCS-2BE');
        }, $desc);

        // 解码 HTML 实体
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // EVE 用 <font> 标签做颜色（ARGB 8位格式），转为 CSS span（RGB 6位）
        $desc = preg_replace_callback('/<font\s+size="?\d+"?\s*color="?#([0-9a-fA-F]+)"?\s*>/i', function ($m) {
            $color = $m[1];
            // EVE ARGB: 前2位是alpha，后6位是RGB
            if (strlen($color) === 8) {
                $color = substr($color, 2);
            }
            return '<span style="color:#' . $color . '">';
        }, $desc);
        $desc = preg_replace_callback('/<font\s+color="?#([0-9a-fA-F]+)"?\s*>/i', function ($m) {
            $color = $m[1];
            if (strlen($color) === 8) {
                $color = substr($color, 2);
            }
            return '<span style="color:#' . $color . '">';
        }, $desc);
        $desc = str_ireplace('</font>', '</span>', $desc);

        // EVE <a href="showinfo:..."> 链接去掉，只保留文本
        $desc = preg_replace('/<a\s+href="showinfo:[^"]*">/i', '', $desc);
        $desc = str_ireplace('</a>', '', $desc);

        // <br> 换行保留
        $desc = preg_replace('/<br\s*\/?>/i', '<br>', $desc);

        // 清理其余不安全标签，只保留安全的 HTML
        $desc = strip_tags($desc, '<br><span><b><i><u><p>');

        // 清理可能残留的无效 UTF-8 字节
        $desc = @iconv('UTF-8', 'UTF-8//IGNORE', $desc);

        return $desc;
    }
}
