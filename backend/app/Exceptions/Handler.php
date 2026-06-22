<?php

namespace App\Exceptions;

use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    protected $levels = [];

    protected $dontReport = [
        TenantIsolationException::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
        });

        $this->renderable(function (TenantIsolationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $httpCode = match ($e->getCode()) {
                    TenantIsolationException::CODE_DATA_NOT_FOUND_OR_DENIED => 404,
                    TenantIsolationException::CODE_CONTEXT_UNINITIALIZED => 401,
                    default => 403,
                };
                return $this->tenantError($e, $httpCode);
            }
        });

        $this->renderable(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->error(
                    '数据校验失败',
                    422,
                    422,
                    ['errors' => $e->errors()],
                    '表单填写有误，请检查后重试'
                );
            }
        });

        $this->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $resource = class_basename($e->getModel());
                return $this->notFoundOrDenied($resource, implode(',', $e->getIds()));
            }
        });

        $this->renderable(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code' => 401,
                    'message' => '未登录或登录已过期',
                    'user_message' => '请先登录后再操作',
                    'error_type' => 'unauthenticated',
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }
        });

        $this->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'code' => 403,
                    'message' => $e->getMessage() ?: '权限不足',
                    'user_message' => '您没有权限执行此操作',
                    'error_type' => 'authorization',
                    'timestamp' => now()->toIso8601String(),
                ], 403);
            }
        });
    }
}
