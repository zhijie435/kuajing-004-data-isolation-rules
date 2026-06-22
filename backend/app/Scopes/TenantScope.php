<?php

namespace App\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    protected $tenantColumn;

    public function __construct($tenantColumn = 'tenant_id')
    {
        $this->tenantColumn = $tenantColumn;
    }

    public function apply(Builder $builder, Model $model)
    {
        if (TenantContext::isSuperAdmin()) {
            return;
        }

        $tenantId = TenantContext::getTenantId();
        if (empty($tenantId)) {
            return;
        }

        $builder->where($model->qualifyColumn($this->tenantColumn), $tenantId);
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('withTenant', function (Builder $builder, $tenantId = null) {
            $tenantId = $tenantId ?: TenantContext::getTenantId();
            if ($tenantId) {
                $model = $builder->getModel();
                $builder->where($model->qualifyColumn($this->tenantColumn), $tenantId);
            }
            return $builder;
        });

        $builder->macro('forAllTenants', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
