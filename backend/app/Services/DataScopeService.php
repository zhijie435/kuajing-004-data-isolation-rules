<?php

namespace App\Services;

use App\Enums\DataScopeEnum;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;

class DataScopeService
{
    public static function getVisibleDeptIds(): array
    {
        $user = TenantContext::getUser();
        if (!$user) {
            return [];
        }

        $dataScope = $user->data_scope ?? DataScopeEnum::SELF;

        switch ($dataScope) {
            case DataScopeEnum::ALL:
                return self::getAllDeptIds($user->tenant_id);

            case DataScopeEnum::TENANT:
                return self::getAllDeptIds($user->tenant_id);

            case DataScopeEnum::DEPARTMENT:
                return [$user->dept_id];

            case DataScopeEnum::DEPARTMENT_AND_SUB:
                return self::getDeptAndSubIds($user->dept_id);

            case DataScopeEnum::CUSTOM:
                return self::getCustomDeptIds($user);

            case DataScopeEnum::SELF:
            default:
                return [];
        }
    }

    public static function getVisibleUserIds(): array
    {
        $user = TenantContext::getUser();
        if (!$user) {
            return [];
        }

        $dataScope = $user->data_scope ?? DataScopeEnum::SELF;

        if ($dataScope === DataScopeEnum::SELF) {
            return [$user->id];
        }

        $deptIds = self::getVisibleDeptIds();
        if (empty($deptIds)) {
            return [$user->id];
        }

        return \App\Models\User::whereIn('dept_id', $deptIds)
            ->pluck('id')
            ->toArray();
    }

    public static function buildFilterParams(): array
    {
        $user = TenantContext::getUser();
        if (!$user) {
            return [];
        }

        $dataScope = $user->data_scope ?? DataScopeEnum::SELF;

        return [
            'tenant_id' => $user->tenant_id,
            'dept_ids' => self::getVisibleDeptIds(),
            'user_ids' => self::getVisibleUserIds(),
            'data_scope' => $dataScope,
            'data_scope_label' => DataScopeEnum::label($dataScope),
            'user_id' => $user->id,
            'dept_id' => $user->dept_id,
        ];
    }

    protected static function getAllDeptIds($tenantId): array
    {
        return \App\Models\Dept::where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();
    }

    protected static function getDeptAndSubIds($deptId): array
    {
        if (!$deptId) {
            return [];
        }

        $dept = \App\Models\Dept::find($deptId);
        if (!$dept) {
            return [];
        }

        return $dept->getSelfAndChildrenIds();
    }

    protected static function getCustomDeptIds($user): array
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $role = $user->role;
        if (!$role) {
            return [];
        }

        return $role->departments()->pluck('sys_dept.id')->toArray();
    }
}
