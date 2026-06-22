<?php

namespace App\Traits;

use App\Scopes\DataScopeScope;

trait HasDataScope
{
    public static function bootHasDataScope(): void
    {
        $config = [
            'user_id_column' => static::$dataScopeUserIdColumn ?? 'created_by',
            'dept_id_column' => static::$dataScopeDeptIdColumn ?? 'dept_id',
            'tenant_id_column' => static::$dataScopeTenantIdColumn ?? 'tenant_id',
        ];
        static::addGlobalScope(new DataScopeScope($config));
    }
}
