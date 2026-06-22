<?php

namespace App\Traits;

use App\Scopes\DataScopeScope;

trait ApiResponseTrait
{
    protected function success($data = null, string $message = 'success', array $extra = []): \Illuminate\Http\JsonResponse
    {
        $response = [
            'code' => 200,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        $scopeWarnings = DataScopeScope::flushAppliedWarnings();
        if (!empty($scopeWarnings)) {
            $response['_scope'] = $scopeWarnings;
            if (DataScopeScope::hasWarnings()) {
                $response['_warning'] = $this->formatScopeWarningMessage($scopeWarnings);
            }
        }

        return response()->json($response);
    }

    protected function error(
        string $message = '操作失败',
        int $httpCode = 400,
        int $bizCode = 400,
        array $details = [],
        string $userMessage = ''
    ): \Illuminate\Http\JsonResponse {
        $response = [
            'code' => $bizCode,
            'message' => $message,
            'user_message' => $userMessage ?: $message,
            'details' => $details,
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($response, $httpCode);
    }

    protected function tenantError(\App\Exceptions\TenantIsolationException $e, int $httpCode = 403): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'user_message' => $e->getUserMessage(),
            'details' => $e->getDetails(),
            'error_type' => 'tenant_isolation',
            'timestamp' => now()->toIso8601String(),
        ], $httpCode);
    }

    protected function notFoundOrDenied(string $resource, $id, string $action = '访问'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 2002,
            'message' => "{$resource}不存在或无权{$action}",
            'user_message' => "数据不存在或您无权{$action}此内容",
            'details' => ['resource' => $resource, 'id' => $id, 'action' => $action],
            'error_type' => 'not_found_or_denied',
            'timestamp' => now()->toIso8601String(),
        ], 404);
    }

    protected function mutationResult(
        string $action,
        string $resource,
        $id,
        bool $success,
        ?int $affectedRows = null,
        array $extra = []
    ): array {
        $messages = [
            'create' => ['成功' => '创建成功', '失败' => '创建失败'],
            'update' => ['成功' => '更新成功', '失败' => '更新失败：数据不存在或无权限'],
            'delete' => ['成功' => '删除成功', '失败' => '删除失败：数据不存在或无权限'],
        ];
        $pair = $messages[$action] ?? ['成功' => '操作成功', '失败' => '操作失败'];

        return array_merge([
            'action' => $action,
            'resource' => $resource,
            'id' => $id,
            'success' => $success,
            'affected_rows' => $affectedRows,
            'user_message' => $success ? $pair['成功'] : $pair['失败'],
        ], $extra);
    }

    protected function formatScopeWarningMessage(array $warnings): string
    {
        $msgs = [];
        foreach ($warnings as $class => $info) {
            if (!empty($info['extra']['from']) && $info['extra']['from'] !== $info['extra']['to']) {
                $shortClass = class_basename($class);
                $msgs[] = sprintf(
                    '%s：%s（原因：%s）',
                    $shortClass,
                    $info['note'],
                    $info['extra']['reason'] ?? '未知'
                );
            }
        }
        return implode('；', $msgs);
    }
}
