<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CharacterController extends Controller
{
    /**
     * 角色列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 获取角色资产概览
        $assets = null;
        if ($user->eve_character_id) {
            $response = Http::withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/assets/');
            
            if ($response->ok()) {
                $assets = $response->json();
            }
        }
        
        return view('characters.index', compact('user', 'assets'));
    }
    
    /**
     * 角色详情
     */
    public function show(Request $request, $characterId)
    {
        $user = $request->user();
        
        // 获取角色详细信息
        $response = Http::get(config('esi.base_url') . 'characters/' . $characterId . '/');
        
        if ($response->failed()) {
            return redirect()->route('characters.index')
                ->with('error', '获取角色信息失败');
        }
        
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

        return view('characters.show', compact('character'));
    }
    
    /**
     * 刷新角色数据
     */
    public function refresh(Request $request, $characterId)
    {
        // 这里可以实现强制刷新逻辑
        return redirect()->back()
            ->with('success', '数据已刷新');
    }
    
    /**
     * 解绑角色
     */
    public function destroy(Request $request, $characterId)
    {
        $user = $request->user();
        
        // 如果是当前用户，不允许删除
        if ($user->eve_character_id == $characterId) {
            return redirect()->back()
                ->with('error', '不能解绑当前登录的角色');
        }
        
        // 实现删除逻辑
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

        return $desc;
    }
}
