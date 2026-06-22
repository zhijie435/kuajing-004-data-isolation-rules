<?php

namespace App\Exceptions;

use Exception;

class TenantIsolationException extends Exception
{
    const CODE_TENANT_MISMATCH = 1001;
    const CODE_TENANT_MODIFY_FORBIDDEN = 1002;
    const CODE_TENANT_DELETE_FORBIDDEN = 1003;
    const CODE_TENANT_EXPIRED = 1004;
    const CODE_DATA_SCOPE_DENIED = 2001;
    const CODE_DATA_NOT_FOUND_OR_DENIED = 2002;
    const CODE_SCOPE_DOWNGRADED = 2003;
    const CODE_ROLE_DATA_SCOPE_INVALID = 3001;
    const CODE_CONTEXT_UNINITIALIZED = 4001;

    protected $details = [];
    protected $userMessage = '';

    public static function tenantMismatch(string $expected, string $actual): self
    {
        $e = new self(
            sprintf('租户信息不匹配：期望=%s，实际=%s', $expected, $actual),
            self::CODE_TENANT_MISMATCH
        );
        $e->userMessage = '租户身份验证失败，请重新登录';
        $e->details = ['expected_tenant_id' => $expected, 'actual_tenant_id' => $actual];
        return $e;
    }

    public static function tenantModifyForbidden(string $original, string $new): self
    {
        $e = new self(
            sprintf('禁止修改数据所属租户：原值=%s，新值=%s', $original, $new),
            self::CODE_TENANT_MODIFY_FORBIDDEN
        );
        $e->userMessage = '无权修改数据所属租户';
        $e->details = ['original_tenant_id' => $original, 'new_tenant_id' => $new];
        return $e;
    }

    public static function tenantDeleteForbidden(string $dataTenant, string $currentTenant): self
    {
        $e = new self(
            sprintf('禁止删除非本租户数据：数据租户=%s，当前租户=%s', $dataTenant, $currentTenant),
            self::CODE_TENANT_DELETE_FORBIDDEN
        );
        $e->userMessage = '无权删除此数据（非本租户）';
        $e->details = ['data_tenant_id' => $dataTenant, 'current_tenant_id' => $currentTenant];
        return $e;
    }

    public static function dataScopeDenied(string $action, string $resource, array $scopeRequired = []): self
    {
        $e = new self(
            sprintf('数据范围权限不足：操作=%s，资源=%s', $action, $resource),
            self::CODE_DATA_SCOPE_DENIED
        );
        $e->userMessage = "当前数据可见范围不允许执行「{$action}」操作";
        $e->details = ['action' => $action, 'resource' => $resource, 'required_scope' => $scopeRequired];
        return $e;
    }

    public static function dataNotFoundOrDenied(string $resource, $id): self
    {
        $e = new self(
            sprintf('数据不存在或无访问权限：资源=%s，ID=%s', $resource, $id),
            self::CODE_DATA_NOT_FOUND_OR_DENIED
        );
        $e->userMessage = "数据不存在或您无权查看此{$resource}";
        $e->details = ['resource' => $resource, 'id' => $id];
        return $e;
    }

    public static function scopeDowngraded(string $from, string $to, string $reason): self
    {
        $e = new self(
            sprintf('数据范围降级：%s → %s，原因=%s', $from, $to, $reason),
            self::CODE_SCOPE_DOWNGRADED
        );
        $e->userMessage = "由于{$reason}，数据可见范围已自动调整";
        $e->details = ['from_scope' => $from, 'to_scope' => $to, 'reason' => $reason];
        return $e;
    }

    public static function contextUninitialized(string $caller = ''): self
    {
        $e = new self(
            '租户上下文未初始化' . ($caller ? "（调用方：{$caller}）" : ''),
            self::CODE_CONTEXT_UNINITIALIZED
        );
        $e->userMessage = '用户会话已过期，请重新登录';
        return $e;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage ?: $this->getMessage();
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'user_message' => $this->getUserMessage(),
            'details' => $this->getDetails(),
        ];
    }
}
