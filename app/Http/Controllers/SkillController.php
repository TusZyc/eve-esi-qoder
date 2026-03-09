<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Helpers\EveHelper;

class SkillController extends Controller
{
    /**
     * 显示技能队列页面
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 检查 Token 是否过期
        if ($user->isTokenExpired()) {
            $this->refreshToken($user);
        }
        
        // 获取技能信息
        $skillsData = $this->getSkillsData($user);
        
        // 获取技能队列
        $skillQueue = $this->getSkillQueue($user);
        
        // 为技能队列添加技能名称（使用统一服务）
        foreach ($skillQueue as &$queueItem) {
            $skillId = $queueItem['skill_id'] ?? 0;
            $queueItem['skill_name'] = EveHelper::getNameById($skillId, 'skill');
        }
        
        // 为已学技能添加技能名称
        if (!empty($skillsData['skills'])) {
            $skillIds = array_column($skillsData['skills'], 'skill_id');
            $skillNames = EveHelper::getNamesByIds($skillIds, 'skill');
            
            foreach ($skillsData['skills'] as &$skill) {
                $skillId = $skill['skill_id'] ?? 0;
                // 确保 skillId 是整数，与数组键名匹配
                $skillId = (int) $skillId;
                $skill['skill_name'] = $skillNames[$skillId] ?? EveHelper::getNameById($skillId, 'skill');
            }
        }
        
        // 计算技能点
        $totalSP = $skillsData['total_sp'] ?? 0;
        $unallocatedSP = $skillsData['unallocated_sp'] ?? 0;
        
        // 计算训练总时间
        $trainingTimeRemaining = 0;
        if (!empty($skillQueue)) {
            foreach ($skillQueue as $queueItem) {
                $trainingTimeRemaining += ($queueItem['finish_date_total'] ?? 0) - time();
            }
        }
        
        return view('skills.index', [
            'user' => $user,
            'skillsData' => $skillsData,
            'skillQueue' => $skillQueue,
            'totalSP' => $totalSP,
            'unallocatedSP' => $unallocatedSP,
            'trainingTimeRemaining' => $trainingTimeRemaining,
        ]);
    }
    
    /**
     * 获取技能数据
     */
    private function getSkillsData($user)
    {
        $cacheKey = 'skills_' . $user->eve_character_id;
        
        return Cache::remember($cacheKey, 300, function() use ($user) {
            $response = Http::withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skills/');
            
            return $response->ok() ? $response->json() : null;
        });
    }
    
    /**
     * 获取技能队列
     */
    private function getSkillQueue($user)
    {
        $cacheKey = 'skillqueue_' . $user->eve_character_id;
        
        return Cache::remember($cacheKey, 60, function() use ($user) {
            $response = Http::withToken($user->access_token)
                ->get(config('esi.base_url') . 'characters/' . $user->eve_character_id . '/skillqueue/');
            
            return $response->ok() ? $response->json() : [];
        });
    }
    
    /**
     * 刷新 Token
     */
    private function refreshToken($user)
    {
        if (empty($user->refresh_token)) {
            return;
        }
        
        $response = Http::withBasicAuth(
            config('esi.client_id'),
            config('esi.client_secret')
        )->post(config('esi.oauth_url') . 'token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token,
        ]);
        
        if ($response->ok()) {
            $tokenData = $response->json();
            $user->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);
        }
    }
}
