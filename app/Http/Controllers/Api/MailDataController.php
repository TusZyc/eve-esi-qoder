<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\TokenService;

class MailDataController extends Controller
{
    /**
     * 缓存时间（秒）
     */
    private const CACHE_TTL = 120;

    /**
     * 获取邮件列表
     */
    public function index(Request $request)
    {
        Log::info('📧 [Mail] 开始获取邮件列表');

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        // 可选参数
        $labels = $request->input('labels');       // 按标签筛选
        $lastMailId = $request->input('last_mail_id'); // 分页

        try {
            $cacheKey = "mail_list_{$characterId}_" . md5(json_encode([$labels, $lastMailId]));
            
            $mails = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseUrl, $characterId, $labels, $lastMailId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $params = ['datasource' => 'serenity'];

                if ($labels) {
                    $params['labels'] = $labels;
                }
                if ($lastMailId) {
                    $params['last_mail_id'] = $lastMailId;
                }

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/mail/", $params);

                if (!$response->ok()) {
                    Log::error('📧 [Mail] 获取邮件列表失败', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    throw new \Exception('ESI request failed for mail list');
                }

                return $response->json();
            });

            if (empty($mails)) {
                return response()->json([]);
            }

            // 获取邮件群组列表（用于解析群组名称）
            $mailingListsMap = $this->getMailingListsMap($characterId, $token, $baseUrl);

            // 收集需要解析的 ID
            $nameIds = [];
            foreach ($mails as $mail) {
                if (!empty($mail['from'])) {
                    $nameIds[] = $mail['from'];
                }
                foreach ($mail['recipients'] ?? [] as $recipient) {
                    // 邮件群组ID不需要通过universe/names解析
                    if (($recipient['recipient_type'] ?? '') !== 'mailing_list') {
                        if (!empty($recipient['recipient_id'])) {
                            $nameIds[] = $recipient['recipient_id'];
                        }
                    }
                }
            }

            // 批量解析名称（带类型信息）
            $resolvedNames = $this->resolveNamesWithTypes(array_unique($nameIds), $token, $baseUrl);

            // 构建返回数据
            $result = [];
            foreach ($mails as $mail) {
                $fromId = $mail['from'] ?? 0;
                $recipients = [];
                $recipientListIds = []; // 收件人中的群组ID列表
                
                foreach ($mail['recipients'] ?? [] as $recipient) {
                    $recId = $recipient['recipient_id'] ?? 0;
                    $recType = $recipient['recipient_type'] ?? 'character';
                    
                    // 如果是邮件群组，从群组映射获取名称
                    $recName = null;
                    if ($recType === 'mailing_list') {
                        $recName = $mailingListsMap[$recId] ?? null;
                        $recipientListIds[] = $recId;
                    } else {
                        $recName = $resolvedNames[$recId]['name'] ?? null;
                    }
                    
                    $recipients[] = [
                        'id' => $recId,
                        'type' => $recType,
                        'name' => $recName,
                    ];
                }

                // 发件人类型
                $fromType = $resolvedNames[$fromId]['category'] ?? 'character';
                $fromName = $resolvedNames[$fromId]['name'] ?? null;

                $result[] = [
                    'mail_id' => $mail['mail_id'] ?? 0,
                    'subject' => $mail['subject'] ?? '(无主题)',
                    'from_id' => $fromId,
                    'from_name' => $fromName,
                    'from_type' => $fromType,
                    'timestamp' => $mail['timestamp'] ?? '',
                    'is_read' => $mail['is_read'] ?? false,
                    'labels' => $mail['labels'] ?? [],
                    'recipients' => $recipients,
                    'mailing_list_ids' => $recipientListIds, // 收件人群组ID列表，用于前端筛选
                ];
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 获取邮件列表异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => '获取邮件列表失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取邮件详情
     */
    public function show(Request $request, $mailId)
    {
        Log::info('📧 [Mail] 获取邮件详情', ['mail_id' => $mailId]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $cacheKey = "mail_detail_{$characterId}_{$mailId}";

            $mail = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseUrl, $characterId, $mailId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return null;

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/mail/{$mailId}/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed for mail detail');
                }

                return $response->json();
            });

            if (!$mail) {
                return response()->json(['error' => '邮件不存在'], 404);
            }

            // 获取邮件群组列表
            $mailingListsMap = $this->getMailingListsMap($characterId, $token, $baseUrl);

            // 收集需要解析的 ID（排除群组）
            $nameIds = [];
            if (!empty($mail['from'])) {
                $nameIds[] = $mail['from'];
            }
            foreach ($mail['recipients'] ?? [] as $recipient) {
                if (($recipient['recipient_type'] ?? '') !== 'mailing_list') {
                    if (!empty($recipient['recipient_id'])) {
                        $nameIds[] = $recipient['recipient_id'];
                    }
                }
            }

            $resolvedNames = $this->resolveNamesWithTypes(array_unique($nameIds), $token, $baseUrl);

            // 构建返回数据
            $fromId = $mail['from'] ?? 0;
            $recipients = [];
            
            foreach ($mail['recipients'] ?? [] as $recipient) {
                $recId = $recipient['recipient_id'] ?? 0;
                $recType = $recipient['recipient_type'] ?? 'character';
                
                $recName = null;
                if ($recType === 'mailing_list') {
                    $recName = $mailingListsMap[$recId] ?? null;
                } else {
                    $recName = $resolvedNames[$recId]['name'] ?? null;
                }
                
                $recipients[] = [
                    'id' => $recId,
                    'type' => $recType,
                    'name' => $recName,
                ];
            }

            $fromType = $resolvedNames[$fromId]['category'] ?? 'character';
            $fromName = $resolvedNames[$fromId]['name'] ?? null;

            $result = [
                'mail_id' => $mail['mail_id'] ?? $mailId,
                'subject' => $mail['subject'] ?? '(无主题)',
                'from_id' => $fromId,
                'from_name' => $fromName,
                'from_type' => $fromType,
                'body' => $mail['body'] ?? '',
                'timestamp' => $mail['timestamp'] ?? '',
                'is_read' => $mail['read'] ?? false,
                'labels' => $mail['labels'] ?? [],
                'recipients' => $recipients,
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 获取邮件详情异常', [
                'error' => $e->getMessage(),
                'mail_id' => $mailId
            ]);
            return response()->json(['error' => '获取邮件详情失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取邮件标签列表
     */
    public function labels(Request $request)
    {
        Log::info('📧 [Mail] 获取邮件标签');

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $cacheKey = "mail_labels_{$characterId}";

            $labels = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return null;

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/mail/labels/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed for mail labels');
                }

                return $response->json();
            });

            if (!$labels) {
                return response()->json(['labels' => [], 'total_unread_count' => 0]);
            }

            // 标签名称中文化映射
            $labelNamesZh = [
                'Inbox' => '收件箱',
                'Sent' => '已发送',
                'Trash' => '回收站',
                '[Corp]' => '[军团]',
                '[Alliance]' => '[联盟]',
            ];

            if (!empty($labels['labels'])) {
                foreach ($labels['labels'] as &$label) {
                    $originalName = $label['name'] ?? '';
                    // 精确匹配
                    if (isset($labelNamesZh[$originalName])) {
                        $label['name'] = $labelNamesZh[$originalName];
                    } 
                    // 部分匹配：[Corp] 或 [Alliance]
                    elseif (strpos($originalName, '[Corp]') !== false) {
                        $label['name'] = str_replace('[Corp]', '[军团]', $originalName);
                    }
                    elseif (strpos($originalName, '[Alliance]') !== false) {
                        $label['name'] = str_replace('[Alliance]', '[联盟]', $originalName);
                    }
                }
            }

            return response()->json($labels);

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 获取邮件标签异常', ['error' => $e->getMessage()]);
            return response()->json(['error' => '获取邮件标签失败'], 500);
        }
    }

    /**
     * 获取邮件列表订阅
     */
    public function lists(Request $request)
    {
        Log::info('📧 [Mail] 获取邮件列表订阅');

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $cacheKey = "mail_lists_{$characterId}";

            $lists = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseUrl, $characterId) {
                $token = TokenService::getToken($characterId);
                if (!$token) return [];

                $response = Http::withToken($token)
                    ->timeout(15)
                    ->get("{$baseUrl}/characters/{$characterId}/mail/lists/", [
                        'datasource' => 'serenity'
                    ]);

                if (!$response->ok()) {
                    throw new \Exception('ESI request failed for mail lists');
                }

                return $response->json();
            });

            return response()->json($lists);

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 获取邮件列表订阅异常', ['error' => $e->getMessage()]);
            return response()->json(['error' => '获取邮件列表订阅失败'], 500);
        }
    }

    /**
     * 发送新邮件
     */
    public function store(Request $request)
    {
        Log::info('📧 [Mail] 发送新邮件');

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.recipient_id' => 'required|integer',
            'recipients.*.recipient_type' => 'required|string|in:character,corporation,alliance,mailing_list',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        try {
            $mailData = [
                'recipients' => $request->input('recipients'),
                'subject' => $request->input('subject'),
                'body' => $request->input('body'),
            ];

            if ($request->has('approved_cost')) {
                $mailData['approved_cost'] = $request->input('approved_cost');
            }

            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$baseUrl}/characters/{$characterId}/mail/", $mailData);

            if ($response->status() === 201) {
                $mailId = $response->json();
                Cache::forget("mail_list_{$characterId}_" . md5(json_encode([null, null])));
                
                return response()->json([
                    'success' => true,
                    'mail_id' => $mailId,
                    'message' => '邮件发送成功'
                ], 201);
            }

            if ($response->status() === 403 && strpos($response->body(), 'CSPA') !== false) {
                return response()->json([
                    'error' => '需要支付CSPA费用',
                    'cspa_required' => true,
                    'details' => $response->json()
                ], 403);
            }

            return response()->json([
                'error' => '发送邮件失败',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 发送邮件异常', ['error' => $e->getMessage()]);
            return response()->json(['error' => '发送邮件失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 更新邮件元数据
     */
    public function update(Request $request, $mailId)
    {
        Log::info('📧 [Mail] 更新邮件元数据', ['mail_id' => $mailId]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $updateData = [];

            if ($request->has('read')) {
                $updateData['read'] = $request->input('read');
            }

            if ($request->has('labels')) {
                $updateData['labels'] = $request->input('labels');
            }

            if (empty($updateData)) {
                return response()->json(['error' => '没有要更新的内容'], 400);
            }

            $response = Http::withToken($token)
                ->timeout(15)
                ->put("{$baseUrl}/characters/{$characterId}/mail/{$mailId}/", $updateData);

            if ($response->status() === 204) {
                Cache::forget("mail_detail_{$characterId}_{$mailId}");
                Cache::forget("mail_list_{$characterId}_" . md5(json_encode([null, null])));

                return response()->json(['success' => true, 'message' => '更新成功']);
            }

            return response()->json([
                'error' => '更新失败',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 更新邮件异常', ['error' => $e->getMessage()]);
            return response()->json(['error' => '更新邮件失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 删除邮件
     */
    public function destroy(Request $request, $mailId)
    {
        Log::info('📧 [Mail] 删除邮件', ['mail_id' => $mailId]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => '用户未登录'], 401);
        }

        $characterId = $user->eve_character_id;
        $token = $user->access_token;
        $baseUrl = rtrim(config('esi.base_url'), '/');

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->delete("{$baseUrl}/characters/{$characterId}/mail/{$mailId}/");

            if ($response->status() === 204) {
                Cache::forget("mail_detail_{$characterId}_{$mailId}");
                Cache::forget("mail_list_{$characterId}_" . md5(json_encode([null, null])));

                return response()->json(['success' => true, 'message' => '删除成功']);
            }

            return response()->json([
                'error' => '删除失败',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('📧 [Mail] 删除邮件异常', ['error' => $e->getMessage()]);
            return response()->json(['error' => '删除邮件失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取邮件群组映射（ID -> 名称）
     */
    private function getMailingListsMap(int $characterId, string $token, string $baseUrl): array
    {
        $cacheKey = "mail_lists_map_{$characterId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($characterId, $baseUrl) {
            $token = TokenService::getToken($characterId);
            if (!$token) return [];

            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$baseUrl}/characters/{$characterId}/mail/lists/", [
                    'datasource' => 'serenity'
                ]);

            if (!$response->ok()) {
                throw new \Exception('ESI request failed for mailing lists map');
            }

            $lists = $response->json();
            $map = [];
            foreach ($lists as $list) {
                if (!empty($list['mailing_list_id']) && !empty($list['name'])) {
                    $map[$list['mailing_list_id']] = $list['name'];
                }
            }

            return $map;
        });
    }

    /**
     * 批量解析角色、军团、联盟名称（带类型信息）
     */
    private function resolveNamesWithTypes(array $ids, string $token, string $baseUrl): array
    {
        if (empty($ids)) return [];

        $ids = array_filter($ids, fn($id) => is_numeric($id) && $id > 0);
        $ids = array_values(array_unique($ids));
        if (empty($ids)) return [];

        $cacheKey = 'esi_names_types_' . md5(implode(',', $ids));

        return Cache::remember($cacheKey, 3600, function () use ($ids, $baseUrl) {
            $map = [];

            $chunks = array_chunk($ids, 1000);

            foreach ($chunks as $chunk) {
                try {
                    $response = Http::timeout(15)
                        ->post("{$baseUrl}/universe/names/?datasource=serenity", $chunk);

                    if ($response->successful()) {
                        foreach ($response->json() as $item) {
                            $map[$item['id']] = [
                                'name' => $item['name'] ?? null,
                                'category' => $item['category'] ?? 'character',
                            ];
                        }
                    } else {
                        throw new \Exception('ESI request failed for universe/names');
                    }
                } catch (\Exception $e) {
                    Log::warning('ESI names 解析失败: ' . $e->getMessage());
                    throw $e;
                }
            }

            return $map;
        });
    }
}