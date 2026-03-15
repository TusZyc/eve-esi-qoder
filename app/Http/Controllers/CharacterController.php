<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Helpers\EveHelper;

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

        try {
            $response = Http::withToken($user->access_token)->timeout(10)
                ->get($baseUrl . 'characters/' . $user->eve_character_id . '/attributes/');

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 403) {
                    return response()->json(['success' => false, 'message' => '权限不足，请重新授权以获取属性数据'], 403);
                }
                return response()->json(['success' => false, 'message' => '获取属性失败'], $status);
            }

            return response()->json(['success' => true, 'data' => $response->json()]);
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

        try {
            $response = Http::withToken($user->access_token)->timeout(10)
                ->get($baseUrl . 'characters/' . $user->eve_character_id . '/implants/');

            if ($response->failed()) {
                $status = $response->status();
                if ($status === 403) {
                    return response()->json(['success' => false, 'message' => '权限不足，请重新授权以获取植入体数据'], 403);
                }
                return response()->json(['success' => false, 'message' => '获取植入体失败'], $status);
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
                // 按名称排序
                usort($implants, fn($a, $b) => strcmp($a['name'], $b['name']));
            }

            return response()->json(['success' => true, 'data' => $implants]);
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

            // 批量解析位置名称 (通过 universe/names 可解析NPC空间站)
            $locationNames = [];
            if (!empty($locationIds)) {
                $locationNames = $this->resolveNames(array_unique($locationIds));
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

            $result = [];
            foreach ($history as $item) {
                $corpId = $item['corporation_id'] ?? 0;
                $startDate = null;
                if (!empty($item['start_date'])) {
                    $startDate = Carbon::parse($item['start_date'])
                        ->timezone('Asia/Shanghai')
                        ->format('Y-m-d H:i');
                }
                $result[] = [
                    'corporation_id' => $corpId,
                    'corporation_name' => $corpNames[$corpId] ?? "Unknown #{$corpId}",
                    'start_date' => $startDate,
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
     * 解析位置名称（空间站或建筑）
     */
    private function resolveLocationName(int $locationId, string $locationType, $user): string
    {
        $cached = Cache::get("eve_location_name_{$locationId}");
        if ($cached !== null) {
            return $cached;
        }

        $baseUrl = config('esi.base_url');
        $name = "Unknown #{$locationId}";

        try {
            if ($locationType === 'structure' && $user) {
                // 玩家建筑需要token
                $response = Http::withToken($user->access_token)->timeout(10)
                    ->get($baseUrl . "universe/structures/{$locationId}/", ['datasource' => 'serenity']);
                if ($response->ok()) {
                    $name = $response->json()['name'] ?? $name;
                }
            } else {
                // NPC空间站
                $response = Http::timeout(10)
                    ->get($baseUrl . "universe/stations/{$locationId}/", ['datasource' => 'serenity']);
                if ($response->ok()) {
                    $name = $response->json()['name'] ?? $name;
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        Cache::put("eve_location_name_{$locationId}", $name, 86400);
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

        // 解码 \uXXXX unicode 转义 → 中文
        $desc = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $desc);

        // 解码 \xNN 十六进制转义 → UTF-8 字符
        $desc = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function ($m) {
            return chr(hexdec($m[1]));
        }, $desc);

        // 解码 HTML 实体
        $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // EVE 用 <font> 标签做颜色，转为 span
        $desc = preg_replace('/<font\s+size="?\d+"?\s*color="?(#[0-9a-fA-F]+)"?\s*>/i', '<span style="color:$1">', $desc);
        $desc = preg_replace('/<font\s+color="?(#[0-9a-fA-F]+)"?\s*>/i', '<span style="color:$1">', $desc);
        $desc = str_ireplace('</font>', '</span>', $desc);

        // EVE <a href="showinfo:..."> 链接去掉，只保留文本
        $desc = preg_replace('/<a\s+href="showinfo:[^"]*">/i', '', $desc);
        $desc = str_ireplace('</a>', '', $desc);

        // <br> 换行保留
        $desc = preg_replace('/<br\s*\/?>/i', '<br>', $desc);

        // 清理其余不安全标签，只保留安全的 HTML
        $desc = strip_tags($desc, '<br><span><b><i><u><p>');

        // 修复 UTF-8 编码
        if (!mb_check_encoding($desc, 'UTF-8')) {
            $desc = mb_convert_encoding($desc, 'UTF-8', 'ISO-8859-1');
        }
        $desc = @iconv('UTF-8', 'UTF-8//IGNORE', $desc);

        return $desc;
    }
}
