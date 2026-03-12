<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CharacterController extends Controller
{
    public function index(Request )
    {
         = ->user();

         = null;
        if (->eve_character_id) {
             = Http::timeout(10)
                ->withToken(->access_token)
                ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/assets/');

            if (->ok()) {
                 = ->json();
            }
        }

        return view('characters.index', compact('user', 'assets'));
    }

    public function show(Request , )
    {
         = ->user();

        // 权限校验：只能查看自己的角色
        if ((int)  !== (int) ->eve_character_id) {
            abort(403, '无权查看该角色信息');
        }

         = Http::timeout(10)
            ->get(config('esi.base_url') . 'characters/' .  . '/');

        if (->failed()) {
            return redirect()->route('characters.index')
                ->with('error', '获取角色信息失败');
        }

         = ->json();

        return view('characters.show', compact('character'));
    }

    public function refresh(Request , )
    {
        return redirect()->back()
            ->with('success', '数据已刷新');
    }

    public function destroy(Request , )
    {
         = ->user();

        if ((int) ->eve_character_id === (int) ) {
            return redirect()->back()
                ->with('error', '不能解绑当前登录的角色');
        }

        return redirect()->route('characters.index')
            ->with('success', '角色已解绑');
    }

    public function skillQueue(Request )
    {
         = ->user();

         = Http::timeout(10)
            ->withToken(->access_token)
            ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/skillqueue/');

        if (->failed()) {
            return response()->json(['error' => '获取技能队列失败'], 400);
        }

        return response()->json(->json());
    }

    public function assets(Request )
    {
         = ->user();

         = Http::timeout(10)
            ->withToken(->access_token)
            ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/assets/');

        if (->failed()) {
            return response()->json(['error' => '获取资产失败'], 400);
        }

        return response()->json(->json());
    }

    public function wallet(Request )
    {
         = ->user();

         = Http::timeout(10)
            ->withToken(->access_token)
            ->get(config('esi.base_url') . 'characters/' . ->eve_character_id . '/wallet/');

        if (->failed()) {
            return response()->json(['error' => '获取钱包信息失败'], 400);
        }

        return response()->json(->json());
    }
}
