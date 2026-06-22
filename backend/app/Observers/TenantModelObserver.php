<?php

namespace App\Observers;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;

class TenantModelObserver
{
    public function creating(Model $model): void
    {
        $tenantColumn = $model::$tenantColumn ?? 'tenant_id';
        $createdByColumn = $model::$createdByColumn ?? 'created_by';

        if (empty($model->{$tenantColumn})) {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId) {
                $model->{$tenantColumn} = $tenantId;
            }
        }

        if (empty($model->{$createdByColumn})) {
            $userId = TenantContext::getUserId();
            if ($userId) {
                $model->{$createdByColumn} = $userId;
            }
        }
    }

    public function updating(Model $model): void
    {
        $tenantColumn = $model::$tenantColumn ?? 'tenant_id';

        if ($model->isDirty($tenantColumn)) {
            $originalTenantId = $model->getOriginal($tenantColumn);
            $currentTenantId = TenantContext::getTenantId();

            if (!TenantContext::isSuperAdmin() && $originalTenantId != $currentTenantId) {
                throw new \Illuminate\Database\QueryException(
                    'UPDATE',
                    [],
                    new \Exception('禁止修改数据所属租户')
                );
            }
        }
    }

    public function deleting(Model $model): void
    {
        $tenantColumn = $model::$tenantColumn ?? 'tenant_id';
        $currentTenantId = TenantContext::getTenantId();

        if (!TenantContext::isSuperAdmin() && $model->{$tenantColumn} != $currentTenantId) {
            throw new \Illuminate\Database\QueryException(
                'DELETE',
                [],
                new \Exception('禁止删除非本租户数据')
            );
        }
    }

    public function saved(Model $model): void
    {
        $model->refresh();

        $tenantColumn = $model::$tenantColumn ?? 'tenant_id';
        if (!empty($model->{$tenantColumn})) {
            $model->{$tenantColumn} = (string) $model->{$tenantColumn};
        }
    }
}
