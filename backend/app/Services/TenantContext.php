<?php

namespace App\Services;

class TenantContext
{
    protected static $tenantId = null;
    protected static $userId = null;
    protected static $user = null;
    protected static $dataScope = null;

    public static function setTenantId($tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function getTenantId()
    {
        return self::$tenantId;
    }

    public static function setUserId($userId): void
    {
        self::$userId = $userId;
    }

    public static function getUserId()
    {
        return self::$userId;
    }

    public static function setUser($user): void
    {
        self::$user = $user;
        self::$userId = $user->id ?? null;
        self::$tenantId = $user->tenant_id ?? null;
        self::$dataScope = $user->data_scope ?? null;
    }

    public static function getUser()
    {
        return self::$user;
    }

    public static function setDataScope($dataScope): void
    {
        self::$dataScope = $dataScope;
    }

    public static function getDataScope()
    {
        return self::$dataScope;
    }

    public static function isSuperAdmin(): bool
    {
        if (!self::$user) {
            return false;
        }
        return in_array(self::$user->role_code ?? '', ['super_admin', 'system_admin']);
    }

    public static function reset(): void
    {
        self::$tenantId = null;
        self::$userId = null;
        self::$user = null;
        self::$dataScope = null;
    }
}
