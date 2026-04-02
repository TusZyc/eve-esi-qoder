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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;

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
        $limit = (int) $request->get('limit', 200);
        
        // 限制 limit 范围
        $limit = max(50, min(500, $limit));
        
        $logs = $this->getLogsByDate($date, $level, $limit);
        
        return view('admin.logs', [
            'user' => $user,
            'activePage' => 'logs',
            'logs' => $logs,
            'currentLevel' => $level,
            'currentDate' => $date,
            'currentLimit' => $limit,
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
                'system_status' => $this->getSystemStatus(),
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
        
        // 清理 UTF-8 编码问题（日志可能包含非 UTF-8 字符）
        $apiStats = $this->sanitizeUtf8Recursive($apiStats);
        
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
    private function getLogsByDate(string $date, string $level = 'all', int $limit = 200): array
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $content = File::get($logFile);
        
        // 构建正则模式 - 使用非捕获组避免索引偏移
        // 'all' 时用 (?:...) 非捕获组，特定级别时直接用字符串
        $levelPattern = $level === 'all' 
            ? '(?:DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY)'
            : strtoupper($level);
        
        $pattern = "/\[($date \d{2}:\d{2}:\d{2})\] \w+\.($levelPattern): (.+?)(?=\[\d{4}-\d{2}-\d{2}|\Z)/s";
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $matches = array_reverse($matches); // 最新的在前
            
            foreach (array_slice($matches, 0, $limit) as $match) {
                $fullMessage = trim($match[3]);
                $firstLine = strtok($fullMessage, "\n");
                
                // 提取 [XXX] 格式的标签
                $tag = '';
                $messageText = $firstLine;
                if (preg_match('/\[([A-Za-z]+)\]\s*/', $firstLine, $tagMatch)) {
                    $tag = $tagMatch[1];
                    // 移除标签前缀，保留消息内容（使用 preg_quote 转义特殊字符）
                    $messageText = trim(preg_replace('/\[' . preg_quote($tag, '/') . '\]\s*/', '', $firstLine));
                }
                
                // 提取 JSON 上下文 - 支持嵌套JSON（如 {"key": {"nested": "value"}}）
                $contextData = null;
                // 匹配消息末尾的JSON，支持一层嵌套
                if (preg_match('/(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})\s*$/', $fullMessage, $jsonMatch)) {
                    $decoded = json_decode($jsonMatch[1], true);
                    if ($decoded !== null) {
                        $contextData = $decoded;
                        // 从消息中移除JSON部分，避免重复显示
                        $messageText = trim(str_replace($jsonMatch[1], '', $messageText));
                    }
                }
                
                $logs[] = [
                    'time' => $match[1],
                    'level' => $match[2],
                    'tag' => $tag,
                    'message' => strlen($messageText) > 300 ? substr($messageText, 0, 300) . '...' : $messageText,
                    'context' => $contextData,
                    'full_message' => strlen($fullMessage) > 2000 ? substr($fullMessage, 0, 2000) . '...' : $fullMessage,
                ];
            }
        }
        
        return $logs;
    }

    /**
     * 递归清理数组中的 UTF-8 编码问题
     */
    private function sanitizeUtf8Recursive($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeUtf8Recursive'], $data);
        }
        
        if (is_string($data)) {
            // 尝试检测并修复编码问题
            if (!mb_check_encoding($data, 'UTF-8')) {
                // 尝试从 GBK/GB2312 转换（常见于中文 Windows 环境）
                $data = mb_convert_encoding($data, 'UTF-8', 'GBK');
            }
            // 确保字符串是有效的 UTF-8
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // 移除任何剩余的无效字符
            $data = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $data);
        }
        
        return $data;
    }

    /**
     * 从日志解析 API 调用统计
     */
    private function parseApiStatsFromLogs(): array
    {
        // 尝试从缓存获取（5分钟缓存）
        $cacheKey = 'admin:api_stats:' . now()->format('Y-m-d-H-i');
        
        return Cache::remember($cacheKey, 300, function () {
            $logFile = storage_path('logs/laravel.log');
            $today = now()->format('Y-m-d');
            
            // 功能模块映射
            $moduleNames = [
                'API' => 'ESI API 调用',
                'Assets' => '资产数据',
                'AutoRefreshToken' => 'Token 自动刷新',
                'TokenRefresh' => 'Token 刷新',
                'Notifications' => '通知系统',
                'Admin' => '管理后台',
                'Market' => '市场数据',
                'Skills' => '技能数据',
                'Contracts' => '合同数据',
                'Wallet' => '钱包数据',
                'Killmails' => 'KM数据',
                'Contacts' => '联系人数据',
                'Bookmarks' => '书签数据',
                'Fittings' => '装配数据',
                'LPStore' => 'LP商店',
                'Character' => '角色数据',
                'Corporation' => '军团数据',
                'Alliance' => '联盟数据',
            ];
            
            // 需要过滤的无意义标签
            $excludeTags = ['stacktrace', 'object', 'previous', 'exception'];
            
            // 初始化统计数据
            $modules = [];
            foreach ($moduleNames as $tag => $name) {
                $modules[$tag] = [
                    'tag' => $tag,
                    'name' => $name,
                    'calls' => 0,
                    'info' => 0,
                    'warning' => 0,
                    'error' => 0,
                ];
            }
            
            // 按小时统计（0-23）
            $hourly = array_fill(0, 24, 0);
            
            // 错误/警告详情列表
            $recentErrors = [];
            
            $totalCalls = 0;
            $totalErrors = 0;
            $totalWarnings = 0;
            
            if (!File::exists($logFile)) {
                return [
                    'modules' => [],
                    'total_calls' => 0,
                    'error_count' => 0,
                    'warning_count' => 0,
                    'hourly' => $hourly,
                    'recent_errors' => [],
                ];
            }
            
            // 逐行读取日志文件（只分析今天的）
            $handle = fopen($logFile, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // 匹配日志格式: [2026-03-20 HH:MM:SS] environment.LEVEL: message
                    if (preg_match('/\[(' . preg_quote($today, '/') . ' (\d{2}):(\d{2}):(\d{2}))\] \w+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): (.+)/i', $line, $match)) {
                        $timestamp = $match[1];
                        $hour = (int) $match[2];
                        $level = strtoupper($match[5]);
                        $message = $match[6];
                        
                        // 提取标签
                        $tag = '';
                        if (preg_match('/\[([A-Za-z][A-Za-z0-9]*)\]/', $message, $tagMatch)) {
                            $potentialTag = $tagMatch[1];
                            // 过滤无意义标签：必须以字母开头，且不在排除列表中
                            if (!in_array(strtolower($potentialTag), $excludeTags) && 
                                preg_match('/^[A-Za-z]/', $potentialTag) &&
                                !preg_match('/^\d+$/', $potentialTag)) {
                                $tag = $potentialTag;
                            }
                        }
                        
                        // 更新小时统计
                        $hourly[$hour]++;
                        
                        // 更新总计
                        $totalCalls++;
                        $isError = in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']);
                        $isWarning = $level === 'WARNING';
                        
                        if ($isError) {
                            $totalErrors++;
                            // 记录错误详情（最多保留20条）
                            if (count($recentErrors) < 20) {
                                $msgSummary = trim(preg_replace('/\[.*?\]\s*/', '', $message));
                                $msgSummary = strlen($msgSummary) > 100 ? substr($msgSummary, 0, 100) . '...' : $msgSummary;
                                $recentErrors[] = [
                                    'time' => $timestamp,
                                    'level' => $level,
                                    'module' => $tag ?: '未知',
                                    'message' => $msgSummary,
                                ];
                            }
                        } elseif ($isWarning) {
                            $totalWarnings++;
                            // 也记录警告（如果错误不够多）
                            if (count($recentErrors) < 20) {
                                $msgSummary = trim(preg_replace('/\[.*?\]\s*/', '', $message));
                                $msgSummary = strlen($msgSummary) > 100 ? substr($msgSummary, 0, 100) . '...' : $msgSummary;
                                $recentErrors[] = [
                                    'time' => $timestamp,
                                    'level' => $level,
                                    'module' => $tag ?: '未知',
                                    'message' => $msgSummary,
                                ];
                            }
                        }
                        
                        // 更新模块统计
                        if ($tag) {
                            // 如果是已知模块
                            if (isset($modules[$tag])) {
                                $modules[$tag]['calls']++;
                                if ($isError) {
                                    $modules[$tag]['error']++;
                                } elseif ($isWarning) {
                                    $modules[$tag]['warning']++;
                                } else {
                                    $modules[$tag]['info']++;
                                }
                            } else {
                                // 动态添加未知但有效的模块
                                $modules[$tag] = [
                                    'tag' => $tag,
                                    'name' => $tag,
                                    'calls' => 1,
                                    'info' => $isError || $isWarning ? 0 : 1,
                                    'warning' => $isWarning ? 1 : 0,
                                    'error' => $isError ? 1 : 0,
                                ];
                            }
                        }
                    }
                }
                fclose($handle);
            }
            
            // 过滤掉没有调用的模块，并按调用次数排序
            $moduleList = array_filter($modules, fn($m) => $m['calls'] > 0);
            usort($moduleList, fn($a, $b) => $b['calls'] - $a['calls']);
            
            // 错误列表按时间倒序（最新在前）
            $recentErrors = array_reverse($recentErrors);
            
            return [
                'modules' => array_values($moduleList),
                'total_calls' => $totalCalls,
                'error_count' => $totalErrors,
                'warning_count' => $totalWarnings,
                'hourly' => $hourly,
                'recent_errors' => $recentErrors,
            ];
        });
    }

    /**
     * 获取系统状态信息
     */
    private function getSystemStatus(): array
    {
        $status = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'redis_status' => 'offline',
            'redis_memory' => '-',
            'redis_keys' => 0,
            'log_size' => 0,
            'data_size' => 0,
        ];

        // Redis 状态
        try {
            $ping = Redis::connection()->ping();
            $status['redis_status'] = ($ping == 'PONG' || $ping === true) ? 'online' : 'offline';
            
            $memoryInfo = Redis::info('memory');
            $status['redis_memory'] = $memoryInfo['used_memory_human'] ?? '-';
            
            $status['redis_keys'] = Redis::dbsize();
        } catch (\Exception $e) {
            $status['redis_status'] = 'error';
            Log::warning('[Admin] Redis 连接失败', ['error' => $e->getMessage()]);
        }

        // 日志文件大小
        $logFile = storage_path('logs/laravel.log');
        if (File::exists($logFile)) {
            $status['log_size'] = File::size($logFile);
        }

        // data 目录大小
        $dataDir = base_path('data');
        if (File::isDirectory($dataDir)) {
            $totalSize = 0;
            foreach (File::allFiles($dataDir) as $file) {
                $totalSize += $file->getSize();
            }
            $status['data_size'] = $totalSize;
        }

        return $status;
    }

    /**
     * 缓存管理页面
     */
    public function cache(Request $request)
    {
        return view('admin.cache', [
            'user' => $request->user(),
            'activePage' => 'cache',
        ]);
    }

    /**
     * API: 获取缓存数据
     */
    public function cacheData(Request $request)
    {
        $data = [
            'redis_status' => 'offline',
            'redis_memory' => '-',
            'redis_keys' => 0,
            'categories' => [],
            'ttl_distribution' => [
                'expiring' => 0,  // <1h
                'normal' => 0,    // 1-24h
                'long' => 0,      // >24h
                'no_ttl' => 0,    // 无过期时间
            ],
        ];

        try {
            $ping = Redis::connection()->ping();
            $data['redis_status'] = ($ping == 'PONG' || $ping === true) ? 'online' : 'offline';
            
            $memoryInfo = Redis::info('memory');
            $data['redis_memory'] = $memoryInfo['used_memory_human'] ?? '-';
            
            $data['redis_keys'] = Redis::dbsize();

            // 按前缀分组统计
            $prefixCounts = [];
            $prefixMemory = [];
            $prefixKeys = [];
            $cursor = 0;
            $prefixMap = [
                'eve_esi_database_location:' => '位置缓存',
                'eve_esi_database_market:' => '市场缓存',
                'eve_esi_database_character:' => '角色缓存',
                'eve_esi_database_corp:' => '军团缓存',
                'eve_esi_database_alliance:' => '联盟缓存',
                'eve_esi_database_universe:' => '宇宙数据',
                'eve_esi_database_esi:' => 'ESI 数据',
                'eve_esi_database_admin:' => '管理后台',
                'eve_esi_database_lp:' => 'LP 商店',
            ];

            // 初始化每个前缀的键列表
            foreach ($prefixMap as $prefix => $name) {
                $prefixKeys[$prefix] = [];
                $prefixMemory[$prefix] = 0;
            }
            $prefixKeys['other'] = [];
            $prefixMemory['other'] = 0;

            // 使用 SCAN 迭代获取键
            do {
                $result = Redis::scan($cursor, ['count' => 1000]);
                if ($result === false) break;
                
                $cursor = $result[0];
                $keys = $result[1] ?? [];
                
                foreach ($keys as $key) {
                    $matched = false;
                    $keyPrefix = 'other';
                    
                    foreach ($prefixMap as $prefix => $name) {
                        if (str_starts_with($key, $prefix)) {
                            $prefixCounts[$prefix] = ($prefixCounts[$prefix] ?? 0) + 1;
                            $keyPrefix = $prefix;
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        $prefixCounts['other'] = ($prefixCounts['other'] ?? 0) + 1;
                    }
                    
                    // 获取TTL和内存
                    $ttl = Redis::ttl($key);
                    $memory = 0;
                    
                    // 尝试获取内存占用（需要 Redis 4.0+）
                    try {
                        $memory = Redis::command('MEMORY', ['USAGE', $key]) ?: 0;
                    } catch (\Exception $e) {
                        // 不支持 MEMORY USAGE 命令，忽略
                    }
                    
                    $prefixMemory[$keyPrefix] = ($prefixMemory[$keyPrefix] ?? 0) + $memory;
                    
                    // 统计TTL分布
                    if ($ttl == -1) {
                        $data['ttl_distribution']['no_ttl']++;
                    } elseif ($ttl == -2) {
                        // 键不存在，忽略
                    } elseif ($ttl < 3600) {
                        $data['ttl_distribution']['expiring']++;
                    } elseif ($ttl <= 86400) {
                        $data['ttl_distribution']['normal']++;
                    } else {
                        $data['ttl_distribution']['long']++;
                    }
                    
                    // 收集每个分类的前5个键
                    if (count($prefixKeys[$keyPrefix]) < 5) {
                        $shortKey = $key;
                        // 去掉前缀显示
                        if ($keyPrefix !== 'other' && str_starts_with($key, $keyPrefix)) {
                            $shortKey = substr($key, strlen($keyPrefix));
                        }
                        
                        $prefixKeys[$keyPrefix][] = [
                            'key' => strlen($shortKey) > 50 ? substr($shortKey, 0, 50) . '...' : $shortKey,
                            'full_key' => $key,
                            'ttl' => $ttl,
                            'ttl_text' => $this->formatTtl($ttl),
                            'memory' => $memory,
                            'memory_text' => $this->formatMemory($memory),
                        ];
                    }
                }
            } while ($cursor != 0);

            // 整理分类数据
            foreach ($prefixMap as $prefix => $name) {
                $count = $prefixCounts[$prefix] ?? 0;
                if ($count > 0) {
                    $data['categories'][] = [
                        'prefix' => $prefix,
                        'name' => $name,
                        'count' => $count,
                        'memory' => $prefixMemory[$prefix] ?? 0,
                        'memory_text' => $this->formatMemory($prefixMemory[$prefix] ?? 0),
                        'keys' => $prefixKeys[$prefix] ?? [],
                    ];
                }
            }
            if (!empty($prefixCounts['other'])) {
                $data['categories'][] = [
                    'prefix' => 'other',
                    'name' => '其他缓存',
                    'count' => $prefixCounts['other'],
                    'memory' => $prefixMemory['other'] ?? 0,
                    'memory_text' => $this->formatMemory($prefixMemory['other'] ?? 0),
                    'keys' => $prefixKeys['other'] ?? [],
                ];
            }

            // 按数量排序
            usort($data['categories'], fn($a, $b) => $b['count'] - $a['count']);

        } catch (\Exception $e) {
            $data['redis_status'] = 'error';
            Log::error('[Admin] 获取缓存数据失败', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 格式化TTL显示
     */
    private function formatTtl(int $ttl): string
    {
        if ($ttl == -1) return '永不过期';
        if ($ttl == -2) return '已过期';
        if ($ttl < 60) return $ttl . '秒';
        if ($ttl < 3600) return floor($ttl / 60) . '分钟';
        if ($ttl < 86400) return floor($ttl / 3600) . '小时';
        return floor($ttl / 86400) . '天';
    }

    /**
     * 格式化内存占用显示
     */
    private function formatMemory(int $bytes): string
    {
        if ($bytes == 0) return '-';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * API: 清除缓存
     */
    public function clearCache(Request $request)
    {
        $type = $request->input('type', 'all');
        $cleared = 0;

        // 分类前缀映射
        $prefixMap = [
            'locations' => 'eve_esi_database_location:',
            'market' => 'eve_esi_database_market:',
            'character' => 'eve_esi_database_character:',
            'corp' => 'eve_esi_database_corp:',
            'alliance' => 'eve_esi_database_alliance:',
            'universe' => 'eve_esi_database_universe:',
            'esi' => 'eve_esi_database_esi:',
            'admin' => 'eve_esi_database_admin:',
            'lp' => 'eve_esi_database_lp:',
        ];

        $nameMap = [
            'locations' => '位置',
            'market' => '市场',
            'character' => '角色',
            'corp' => '军团',
            'alliance' => '联盟',
            'universe' => '宇宙数据',
            'esi' => 'ESI数据',
            'admin' => '管理后台',
            'lp' => 'LP商店',
        ];

        try {
            if ($type === 'views') {
                Artisan::call('view:clear');
                Log::info('[Admin] 清除视图缓存', ['admin' => $request->user()->name]);
                return response()->json(['success' => true, 'message' => '视图缓存已清除']);
            }

            if ($type === 'all') {
                Redis::flushdb();
                Artisan::call('view:clear');
                Log::info('[Admin] 清除全部缓存', ['admin' => $request->user()->name]);
                return response()->json(['success' => true, 'message' => '全部缓存已清除']);
            }

            // 按分类清除
            if (isset($prefixMap[$type])) {
                $cleared = $this->clearCacheByPrefix($prefixMap[$type]);
                $typeName = $nameMap[$type] ?? $type;
                Log::info("[Admin] 清除{$typeName}缓存", ['admin' => $request->user()->name, 'count' => $cleared]);
                return response()->json(['success' => true, 'message' => "已清除 {$cleared} 条{$typeName}缓存"]);
            }

            // 支持直接传前缀
            if (str_starts_with($type, 'eve_esi_database_')) {
                $cleared = $this->clearCacheByPrefix($type);
                Log::info('[Admin] 清除指定前缀缓存', ['admin' => $request->user()->name, 'prefix' => $type, 'count' => $cleared]);
                return response()->json(['success' => true, 'message' => "已清除 {$cleared} 条缓存"]);
            }

            return response()->json(['success' => false, 'message' => '未知的缓存类型'], 400);
        } catch (\Exception $e) {
            Log::error('[Admin] 清除缓存失败', ['type' => $type, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => '清除失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 按前缀清除缓存
     */
    private function clearCacheByPrefix(string $prefix): int
    {
        $cleared = 0;
        $cursor = 0;

        do {
            $result = Redis::scan($cursor, ['match' => $prefix . '*', 'count' => 1000]);
            if ($result === false) break;
            
            $cursor = $result[0];
            $keys = $result[1] ?? [];
            
            if (!empty($keys)) {
                Redis::del(...$keys);
                $cleared += count($keys);
            }
        } while ($cursor != 0);

        return $cleared;
    }

    /**
     * 数据管理页面
     */
    public function dataManage(Request $request)
    {
        return view('admin.data', [
            'user' => $request->user(),
            'activePage' => 'data',
        ]);
    }

    /**
     * API: 获取数据文件信息
     */
    public function dataInfo(Request $request)
    {
        $dataDir = base_path('data');
        $files = [];
        $totalSize = 0;
        $latestUpdate = null;
        $now = time();

        // 文件描述映射
        $fileDescriptions = [
            'eve_items.json' => '物品ID-名称映射（来自evedata.xlsx）',
            'eve_systems.json' => '星系数据（ID、名称、安全等级）',
            'eve_regions.json' => '星域数据',
            'eve_constellations.json' => '星座数据',
            'eve_stations.json' => 'NPC空间站数据',
            'eve_structures.json' => '玩家公开建筑数据',
            'items.json' => '旧版物品数据（兼容用）',
            'eve_systems_full.json' => '完整星系数据（含坐标）',
            'solar_system_jumps.json' => '星系跳跃连接图',
            'faction_corps_map.json' => '阵营-军团映射',
            'lp_factions.json' => 'LP商店阵营数据',
            'lp_offers.json' => 'LP商店兑换数据',
        ];

        if (File::isDirectory($dataDir)) {
            foreach (File::files($dataDir) as $file) {
                $filename = $file->getFilename();
                if (!str_ends_with($filename, '.json')) continue;

                $size = $file->getSize();
                $mtime = $file->getMTime();
                $totalSize += $size;

                if ($latestUpdate === null || $mtime > $latestUpdate) {
                    $latestUpdate = $mtime;
                }

                // 计算新鲜度
                $ageSeconds = $now - $mtime;
                $freshness = $this->formatFreshness($ageSeconds);
                $isStale = $ageSeconds > 7 * 24 * 3600; // 超过7天为过期

                // 获取记录数和预览
                $recordCount = '-';
                $preview = null;
                try {
                    $content = File::get($file->getPathname());
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $recordCount = count($data);
                        // 获取前3条记录作为预览
                        $previewData = array_slice($data, 0, 3);
                        $preview = $previewData;
                    }
                } catch (\Exception $e) {
                    // 忽略解析错误
                }

                $files[] = [
                    'name' => $filename,
                    'description' => $fileDescriptions[$filename] ?? '未知用途',
                    'size' => $size,
                    'records' => $recordCount,
                    'updated_at' => date('Y-m-d H:i:s', $mtime),
                    'mtime' => $mtime,
                    'freshness' => $freshness,
                    'is_stale' => $isStale,
                    'preview' => $preview,
                ];
            }
        }

        // 按文件名排序
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json([
            'success' => true,
            'data' => [
                'file_count' => count($files),
                'total_size' => $totalSize,
                'latest_update' => $latestUpdate ? date('Y-m-d H:i:s', $latestUpdate) : '-',
                'files' => $files,
            ],
        ]);
    }

    /**
     * 格式化数据新鲜度
     */
    private function formatFreshness(int $seconds): string
    {
        if ($seconds < 60) return '刚刚';
        if ($seconds < 3600) return floor($seconds / 60) . '分钟前';
        if ($seconds < 86400) return floor($seconds / 3600) . '小时前';
        if ($seconds < 604800) return floor($seconds / 86400) . '天前';
        if ($seconds < 2592000) return floor($seconds / 604800) . '周前';
        return floor($seconds / 2592000) . '个月前';
    }
}
