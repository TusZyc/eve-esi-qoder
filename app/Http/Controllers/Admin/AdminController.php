<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * 国服 ESI 图片基础 URL
     */
    private const EVE_IMAGE_BASE = 'https://images.evepc.com';

    /**
     * 检测 refresh token 是否有效（尝试刷新）
     */
    private function checkRefreshTokenValid(User $user): array
    {
        if (!$user->refresh_token) {
            return ['valid' => false, 'error' => '无 refresh token'];
        }

        try {
            $response = Http::asForm()->post(
                config('esi.oauth_url') . 'token',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]
            );

            if ($response->ok()) {
                return ['valid' => true, 'error' => null];
            }

            $errorData = $response->json();
            return [
                'valid' => false,
                'error' => $errorData['error_description'] ?? $errorData['error'] ?? '刷新失败'
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 刷新用户的 access token
     */
    public function refreshToken(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if (!$user->eve_character_id) {
            return response()->json([
                'success' => false,
                'message' => '该用户未授权 EVE 角色',
            ], 400);
        }

        if (!$user->refresh_token) {
            return response()->json([
                'success' => false,
                'message' => '该用户没有 refresh token',
            ], 400);
        }

        try {
            $response = Http::asForm()->post(
                config('esi.oauth_url') . 'token',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->refresh_token,
                    'client_id' => config('esi.client_id'),
                ]
            );

            if ($response->ok()) {
                $tokenData = $response->json();

                $user->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? $user->refresh_token,
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                ]);

                Log::info('✅ [Admin] 管理员手动刷新用户 Token 成功', [
                    'admin' => $request->user()->name,
                    'target_user' => $user->name,
                    'new_expires_at' => $user->getTokenExpiryFormatted(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Token 刷新成功',
                    'data' => [
                        'token_expires_at' => $user->token_expires_at->format('Y-m-d H:i:s'),
                    ],
                ]);
            }

            $errorData = $response->json();
            $errorMsg = $errorData['error_description'] ?? $errorData['error'] ?? '未知错误';

            Log::error('❌ [Admin] 管理员手动刷新用户 Token 失败', [
                'admin' => $request->user()->name,
                'target_user' => $user->name,
                'error' => $errorMsg,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token 刷新失败: ' . $errorMsg,
            ], 400);

        } catch (\Exception $e) {
            Log::error('❌ [Admin] Token 刷新异常', [
                'admin' => $request->user()->name,
                'target_user' => $user->name,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token 刷新异常: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取用户授权状态信息
     */
    private function getUserAuthStatus(User $user): array
    {
        // 检查当前 token 状态
        $tokenExpired = $user->isTokenExpired();
        $tokenExpiring = $user->shouldRefreshToken();
        
        if ($tokenExpired) {
            $currentStatus = 'expired';
            $currentStatusText = '已过期';
        } elseif ($tokenExpiring) {
            $currentStatus = 'expiring';
            $currentStatusText = '即将过期';
        } else {
            $currentStatus = 'valid';
            $currentStatusText = '有效';
        }

        // 检查 refresh token 是否有效（仅当 access token 过期或即将过期时检查）
        $authStatus = 'unknown';
        $authStatusText = '未知';
        
        if (!$user->refresh_token) {
            $authStatus = 'none';
            $authStatusText = '未授权';
        } elseif ($currentStatus === 'valid') {
            // access token 有效，说明 refresh token 肯定有效
            $authStatus = 'valid';
            $authStatusText = '有效';
        } else {
            // access token 过期时，尝试用 refresh token 刷新来验证
            $checkResult = $this->checkRefreshTokenValid($user);
            if ($checkResult['valid']) {
                $authStatus = 'valid';
                $authStatusText = '有效';
            } else {
                $authStatus = 'expired';
                $authStatusText = '已过期';
            }
        }

        return [
            'auth_status' => $authStatus,
            'auth_status_text' => $authStatusText,
            'current_status' => $currentStatus,
            'current_status_text' => $currentStatusText,
        ];
    }
    /**
     * 管理首页 - 显示概览信息
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        // 获取已授权用户列表
        $authorizedUsers = User::whereNotNull('eve_character_id')
            ->orderBy('updated_at', 'desc')
            ->get();
        
        // 获取最近的错误日志
        $recentErrors = $this->getRecentErrors(10);
        
        // 获取日志统计
        $logStats = $this->getLogStats();
        
        return view('admin.dashboard', [
            'user' => $user,
            'activePage' => 'dashboard',
            'authorizedUsers' => $authorizedUsers,
            'recentErrors' => $recentErrors,
            'logStats' => $logStats,
        ]);
    }

    /**
     * 用户管理页面
     */
    public function users(Request $request)
    {
        $user = $request->user();
        
        $users = User::orderBy('updated_at', 'desc')->paginate(20);
        
        // 为每个用户计算授权状态
        $users->getCollection()->transform(function ($u) {
            $authStatus = $this->getUserAuthStatus($u);
            $u->auth_status = $authStatus['auth_status'];
            $u->auth_status_text = $authStatus['auth_status_text'];
            $u->current_status = $authStatus['current_status'];
            $u->current_status_text = $authStatus['current_status_text'];
            return $u;
        });
        
        return view('admin.users', [
            'user' => $user,
            'activePage' => 'users',
            'users' => $users,
            'eveImageBase' => self::EVE_IMAGE_BASE,
        ]);
    }

    /**
     * 系统日志页面
     */
    public function logs(Request $request)
    {
        $user = $request->user();
        
        $level = $request->get('level', 'all');
        $date = $request->get('date', now()->format('Y-m-d'));
        
        $logs = $this->getLogsByDate($date, $level, 100);
        
        return view('admin.logs', [
            'user' => $user,
            'activePage' => 'logs',
            'logs' => $logs,
            'currentLevel' => $level,
            'currentDate' => $date,
        ]);
    }

    /**
     * API 统计页面
     */
    public function apiStats(Request $request)
    {
        $user = $request->user();
        
        return view('admin.api-stats', [
            'user' => $user,
            'activePage' => 'api-stats',
        ]);
    }

    /**
     * API: 获取管理首页数据（异步加载）
     */
    public function dashboardData(Request $request)
    {
        // 获取已授权用户列表
        $authorizedUsers = User::whereNotNull('eve_character_id')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($u) {
                $authStatus = $this->getUserAuthStatus($u);
                
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'eve_character_id' => $u->eve_character_id,
                    'corporation_id' => $u->corporation_id,
                    'alliance_id' => $u->alliance_id,
                    'token_expires_at' => $u->token_expires_at?->format('Y-m-d H:i:s'),
                    'auth_status' => $authStatus['auth_status'],
                    'auth_status_text' => $authStatus['auth_status_text'],
                    'current_status' => $authStatus['current_status'],
                    'current_status_text' => $authStatus['current_status_text'],
                    'last_active' => $u->updated_at?->format('Y-m-d H:i:s'),
                ];
            });
        
        // 获取最近的错误日志
        $recentErrors = $this->getRecentErrors(10);
        
        // 获取日志统计
        $logStats = $this->getLogStats();
        
        return response()->json([
            'success' => true,
            'data' => [
                'authorized_users' => $authorizedUsers,
                'user_count' => $authorizedUsers->count(),
                'recent_errors' => $recentErrors,
                'log_stats' => $logStats,
            ],
        ]);
    }

    /**
     * API: 获取 API 调用统计
     */
    public function apiStatsData(Request $request)
    {
        // 从日志中解析 API 调用统计
        $apiStats = $this->parseApiStatsFromLogs();
        
        return response()->json([
            'success' => true,
            'data' => $apiStats,
        ]);
    }

    /**
     * 获取最近的错误日志
     */
    private function getRecentErrors(int $limit = 10): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [];
        }
        
        $errors = [];
        $content = File::get($logFile);
        
        // 解析日志文件，提取错误
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.+?)(?=\[\d{4}-\d{2}-\d{2}|\Z)/s';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $matches = array_reverse($matches); // 最新的在前
            
            foreach (array_slice($matches, 0, $limit) as $match) {
                $message = trim($match[3]);
                // 截取第一行作为摘要
                $firstLine = strtok($message, "\n");
                
                $errors[] = [
                    'time' => $match[1],
                    'level' => $match[2],
                    'message' => strlen($firstLine) > 200 ? substr($firstLine, 0, 200) . '...' : $firstLine,
                    'full_message' => strlen($message) > 1000 ? substr($message, 0, 1000) . '...' : $message,
                ];
            }
        }
        
        return $errors;
    }

    /**
     * 获取日志统计
     */
    private function getLogStats(): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        $stats = [
            'error' => 0,
            'warning' => 0,
            'info' => 0,
            'debug' => 0,
            'total_size' => 0,
        ];
        
        if (!File::exists($logFile)) {
            return $stats;
        }
        
        $stats['total_size'] = File::size($logFile);
        
        $content = File::get($logFile);
        
        // 只统计今天的日志
        $today = now()->format('Y-m-d');
        
        $stats['error'] = preg_match_all("/\[$today.*?\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY)/", $content);
        $stats['warning'] = preg_match_all("/\[$today.*?\] \w+\.WARNING/", $content);
        $stats['info'] = preg_match_all("/\[$today.*?\] \w+\.INFO/", $content);
        $stats['debug'] = preg_match_all("/\[$today.*?\] \w+\.DEBUG/", $content);
        
        return $stats;
    }

    /**
     * 按日期获取日志
     */
    private function getLogsByDate(string $date, string $level = 'all', int $limit = 100): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $content = File::get($logFile);
        
        // 构建正则模式
        $levelPattern = $level === 'all' 
            ? '(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)'
            : strtoupper($level);
        
        $pattern = "/\[($date \d{2}:\d{2}:\d{2})\] \w+\.($levelPattern): (.+?)(?=\[\d{4}-\d{2}-\d{2}|\Z)/s";
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $matches = array_reverse($matches); // 最新的在前
            
            foreach (array_slice($matches, 0, $limit) as $match) {
                $message = trim($match[3]);
                $firstLine = strtok($message, "\n");
                
                $logs[] = [
                    'time' => $match[1],
                    'level' => $match[2],
                    'message' => strlen($firstLine) > 300 ? substr($firstLine, 0, 300) . '...' : $firstLine,
                ];
            }
        }
        
        return $logs;
    }

    /**
     * 从日志解析 API 调用统计
     */
    private function parseApiStatsFromLogs(): array
    {
        // 尝试从缓存获取
        $cacheKey = 'admin:api_stats:' . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, 300, function () {
            $logFile = storage_path('logs/laravel.log');
            
            $stats = [
                'endpoints' => [],
                'total_calls' => 0,
                'error_rate' => 0,
                'avg_response_time' => 0,
            ];
            
            if (!File::exists($logFile)) {
                return $stats;
            }
            
            $content = File::get($logFile);
            $today = now()->format('Y-m-d');
            
            // 统计各 API 端点的调用情况（通过分析请求日志）
            $apiPatterns = [
                '/api/dashboard/server-status' => '服务器状态',
                '/api/dashboard/skills' => '技能数据',
                '/api/dashboard/skill-queue' => '技能队列',
                '/api/dashboard/assets' => '资产数据',
                '/api/dashboard/character' => '角色数据',
                '/api/public/market' => '市场数据',
                '/api/public/server-status' => '公开服务器状态',
                '/api/capital-nav' => '旗舰导航',
                '/api/killmails' => 'KM查询',
            ];
            
            foreach ($apiPatterns as $pattern => $name) {
                // 简单统计出现次数
                $count = substr_count($content, $pattern);
                if ($count > 0) {
                    $stats['endpoints'][] = [
                        'path' => $pattern,
                        'name' => $name,
                        'calls' => $count,
                        'errors' => 0, // 需要更复杂的解析
                        'avg_time' => '-', // 需要专门的性能日志
                    ];
                    $stats['total_calls'] += $count;
                }
            }
            
            // 按调用次数排序
            usort($stats['endpoints'], fn($a, $b) => $b['calls'] - $a['calls']);
            
            return $stats;
        });
    }
}
