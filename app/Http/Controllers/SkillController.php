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
        
        // 检查是否需要刷新 Token（过期或即将过期）
        if ($user->isTokenExpired() || $user->shouldRefreshToken()) {
            \Log::info('检测到 Token 需要刷新，过期时间：' . $user->getTokenExpiryFormatted());
            $this->refreshToken($user);
        } else {
            \Log::info('Token 有效，剩余时间：' . $user->getTokenRemainingSeconds() . ' 秒');
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
        
        // 为已学技能添加技能名称和分组信息
        if (!empty($skillsData['skills'])) {
            $skillIds = array_column($skillsData['skills'], 'skill_id');
            $skillNames = EveHelper::getNamesByIds($skillIds, 'skill');
            $skillGroups = $this->getSkillGroups($skillIds);
            
            foreach ($skillsData['skills'] as &$skill) {
                $skillId = $skill['skill_id'] ?? 0;
                $skillId = (int) $skillId;
                $skill['skill_name'] = $skillNames[$skillId] ?? EveHelper::getNameById($skillId, 'skill');
                $skill['group_name'] = $skillGroups[$skillId]['group_name'] ?? '其他';
                $skill['group_id'] = $skillGroups[$skillId]['group_id'] ?? 0;
            }
            
            // 按分组排序技能
            $skillsData['skills_by_group'] = $this->groupSkillsByCategory($skillsData['skills']);
        }
        
        // 计算技能点
        $totalSP = $skillsData['total_sp'] ?? 0;
        $unallocatedSP = $skillsData['unallocated_sp'] ?? 0;
        
        // 计算训练总剩余时间（所有未完成技能的剩余时间总和）
        $trainingTimeRemaining = 0;
        $now = time();
        if (!empty($skillQueue)) {
            foreach ($skillQueue as $queueItem) {
                $finishDate = isset($queueItem['finish_date']) ? strtotime($queueItem['finish_date']) : 0;
                if ($finishDate > $now) {
                    $trainingTimeRemaining += ($finishDate - $now);
                }
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
     * 获取技能分组信息（简化版：先不显示分组，只显示技能列表）
     */
    private function getSkillGroups($skillIds)
    {
        // 暂时返回空数组，技能直接显示
        return [];
    }
    
    /**
     * 按类别分组技能（简化版）
     */
    private function groupSkillsByCategory($skills)
    {
        // 暂时不分组，直接返回所有技能
        return ['all' => ['group_id' => 0, 'group_name' => '所有技能', 'skills' => $skills]];
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
            \Log::warning('Refresh Token 为空，无法刷新');
            return;
        }
        
        \Log::info('=== 开始刷新 Token ===');
        \Log::info('角色：' . $user->name . ' (ID: ' . $user->eve_character_id . ')');
        
        // 国服 OAuth2 不需要 client_secret，直接在 body 中传递 client_id
        $response = Http::asForm()->post(config('esi.oauth_url') . 'token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->refresh_token,
            'client_id' => config('esi.client_id'),
        ]);
        
        \Log::info('Token 刷新响应状态：' . $response->status());
        
        if ($response->ok()) {
            $tokenData = $response->json();
            \Log::info('✅ Token 刷新成功');
            
            $user->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);
            
            \Log::info('✅ Token 已更新到数据库');
        } else {
            \Log::error('❌ Token 刷新失败：' . $response->body());
            
            // 如果刷新失败（可能是 Refresh Token 过期），需要重新授权
            if ($response->status() === 400 || $response->status() === 401) {
                \Log::warning('Refresh Token 可能已过期，需要重新授权');
                session()->flash('error', '授权已过期，请重新授权。');
            }
        }
    }
}
