<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
}
