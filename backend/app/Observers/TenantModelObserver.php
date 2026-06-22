<?php

namespace App\Observers;

use App\Exceptions\TenantIsolationException;
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
            } else {
                throw TenantIsolationException::contextUninitialized(get_class($model) . '::creating');
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
            $originalTenantId = (string) $model->getOriginal($tenantColumn);
            $newTenantId = (string) $model->{$tenantColumn};
            $currentTenantId = (string) TenantContext::getTenantId();

            if (!TenantContext::isSuperAdmin() && $originalTenantId !== $newTenantId) {
                throw TenantIsolationException::tenantModifyForbidden($originalTenantId, $newTenantId);
            }

            if (!TenantContext::isSuperAdmin() && $newTenantId !== $currentTenantId) {
                throw TenantIsolationException::tenantMismatch($currentTenantId, $newTenantId);
            }
        }

        $userIdColumn = $model::$createdByColumn ?? 'created_by';
        if ($model->isDirty($userIdColumn) && !TenantContext::isSuperAdmin()) {
            $originalCreator = (string) $model->getOriginal($userIdColumn);
            $newCreator = (string) $model->{$userIdColumn};
            if ($originalCreator !== '' && $originalCreator !== $newCreator) {
                throw TenantIsolationException::dataScopeDenied(
                    '修改创建人',
                    class_basename($model),
                    ['SCOPE_SUPER_ADMIN']
                );
            }
        }
    }

    public function deleting(Model $model): void
    {
        if (TenantContext::isSuperAdmin()) {
            return;
        }

        $tenantColumn = $model::$tenantColumn ?? 'tenant_id';
        $dataTenantId = (string) $model->{$tenantColumn};
        $currentTenantId = (string) TenantContext::getTenantId();

        if ($dataTenantId !== $currentTenantId) {
            throw TenantIsolationException::tenantDeleteForbidden($dataTenantId, $currentTenantId);
        }

        $userIdColumn = $model::$createdByColumn ?? 'created_by';
        if (isset($model->{$userIdColumn})) {
            $dataCreator = (string) $model->{$userIdColumn};
            $currentUserId = (string) TenantContext::getUserId();

            if ($dataCreator !== '' && $dataCreator !== $currentUserId) {
                throw TenantIsolationException::dataScopeDenied(
                    '删除',
                    class_basename($model),
                    ['SCOPE_DEPARTMENT_AND_SUB']
                );
            }
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
