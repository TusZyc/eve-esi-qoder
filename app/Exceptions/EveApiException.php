<?php

namespace App\Exceptions;

use Exception;

/**
 * EVE API 统一异常类
 * 
 * 用于处理 EVE ESI API 相关的错误，提供结构化的错误响应
 */
class EveApiException extends Exception
{
    /**
     * 错误代码标识
     */
    public string $errorCode;
    
    /**
     * HTTP 状态码
     */
    public int $httpStatus;
    
    /**
     * 构造函数
     *
     * @param string $errorCode 错误代码标识（如 'token_expired', 'unauthorized'）
     * @param string $message 错误消息
     * @param int $httpStatus HTTP 状态码
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(string $errorCode, string $message = '', int $httpStatus = 400, ?Exception $previous = null)
    {
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        parent::__construct($message, 0, $previous);
    }
    
    /**
     * 转换为 JSON 响应
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse()
    {
        return response()->json([
            'success' => false,
            'error' => $this->errorCode,
            'message' => $this->message,
        ], $this->httpStatus);
    }
    
    /**
     * 创建未授权异常
     */
    public static function unauthorized(string $message = '未授权，请重新登录'): self
    {
        return new self('unauthorized', $message, 401);
    }
    
    /**
     * 创建 Token 过期异常
     */
    public static function tokenExpired(string $message = 'Token已过期，请重新授权'): self
    {
        return new self('token_expired', $message, 401);
    }
    
    /**
     * 创建服务不可用异常
     */
    public static function serviceUnavailable(string $message = 'ESI服务暂时不可用'): self
    {
        return new self('service_unavailable', $message, 503);
    }
    
    /**
     * 创建连接超时异常
     */
    public static function connectionTimeout(string $message = 'ESI连接超时'): self
    {
        return new self('connection_timeout', $message, 503);
    }
}
