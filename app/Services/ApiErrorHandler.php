<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Client\Response;
use App\Exceptions\EveApiException;
use Illuminate\Support\Facades\Log;

/**
 * API 统一错误处理服务
 * 
 * 提供通用的认证检查和响应处理方法
 */
class ApiErrorHandler
{
    /**
     * 检查用户是否已认证并有有效的 EVE 角色
     *
     * @param Request $request
     * @return \App\Models\User
     * @throws EveApiException
     */
    public static function requireAuth(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->eve_character_id) {
            throw new EveApiException('unauthorized', '未授权，请重新登录', 401);
        }
        
        if (empty($user->access_token)) {
            throw new EveApiException('no_token', '缺少访问令牌', 401);
        }
        
        return $user;
    }
    
    /**
     * 处理 ESI API 响应
     *
     * @param Response $response HTTP 响应对象
     * @param string $context 上下文说明（用于日志）
     * @return array JSON 解析后的数据
     * @throws EveApiException
     */
    public static function handleResponse(Response $response, string $context = '')
    {
        if ($response->ok()) {
            return $response->json();
        }
        
        $status = $response->status();
        $logContext = $context ? " ({$context})" : '';
        
        if ($status === 401 || $status === 403) {
            Log::warning("ESI Token 失效{$logContext}", ['status' => $status]);
            throw new EveApiException('token_expired', 'Token已过期，请重新授权', 401);
        }
        
        if ($status === 503) {
            Log::warning("ESI 服务不可用{$logContext}", ['status' => $status]);
            throw new EveApiException('service_unavailable', 'ESI服务暂时不可用', 503);
        }
        
        if ($status === 504) {
            Log::warning("ESI 网关超时{$logContext}", ['status' => $status]);
            throw new EveApiException('gateway_timeout', 'ESI网关超时', 504);
        }
        
        Log::error("ESI 请求失败{$logContext}", [
            'status' => $status,
            'body' => substr($response->body(), 0, 200),
        ]);
        throw new EveApiException('http_error', "ESI请求失败: HTTP {$status}", $status);
    }
    
    /**
     * 处理异常并转换为 EveApiException
     *
     * @param \Exception $e 原始异常
     * @param string $context 上下文说明
     * @throws EveApiException
     */
    public static function handleException(\Exception $e, string $context = '')
    {
        // 如果已经是 EveApiException，直接重新抛出
        if ($e instanceof EveApiException) {
            throw $e;
        }
        
        $logContext = $context ? " ({$context})" : '';
        
        // 连接超时
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            Log::warning("ESI 连接超时{$logContext}", ['error' => $e->getMessage()]);
            throw new EveApiException('connection_timeout', 'ESI连接超时', 503, $e);
        }
        
        // 其他未知错误
        Log::error("ESI 未知错误{$logContext}", ['error' => $e->getMessage()]);
        throw new EveApiException('unknown_error', $e->getMessage(), 500, $e);
    }
    
    /**
     * 快速返回成功响应
     *
     * @param mixed $data 响应数据
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success($data)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    
    /**
     * 快速返回错误响应
     *
     * @param string $errorCode 错误代码
     * @param string $message 错误消息
     * @param int $status HTTP 状态码
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error(string $errorCode, string $message, int $status = 400)
    {
        return response()->json([
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
        ], $status);
    }
}
