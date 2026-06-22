<?php

namespace App\Traits;

use App\Scopes\TenantScope;

trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        $tenantColumn = static::$tenantColumn ?? 'tenant_id';
        static::addGlobalScope(new TenantScope($tenantColumn));
    }

    public function setTenantAttribute($value): void
    {
        $tenantColumn = static::$tenantColumn ?? 'tenant_id';
        $this->attributes[$tenantColumn] = $value;
    }

    public function getTenantAttribute()
    {
        $tenantColumn = static::$tenantColumn ?? 'tenant_id';
        return $this->attributes[$tenantColumn] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $tenantColumn = static::$tenantColumn ?? 'tenant_id';
            if (empty($model->{$tenantColumn})) {
                $tenantId = \App\Services\TenantContext::getTenantId();
                if ($tenantId) {
                    $model->{$tenantColumn} = $tenantId;
                }
            }
        });
    }
}
