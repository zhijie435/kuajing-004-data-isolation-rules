<?php

namespace App\Scopes;

use App\Enums\DataScopeEnum;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class DataScopeScope implements Scope
{
    protected $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'user_id_column' => 'created_by',
            'dept_id_column' => 'dept_id',
            'tenant_id_column' => 'tenant_id',
        ], $config);
    }

    public function apply(Builder $builder, Model $model)
    {
        if (TenantContext::isSuperAdmin()) {
            return;
        }

        $user = TenantContext::getUser();
        if (empty($user)) {
            return;
        }

        $dataScope = $user->data_scope ?? DataScopeEnum::SELF;

        switch ($dataScope) {
            case DataScopeEnum::ALL:
                $this->applyAllScope($builder, $model, $user);
                break;

            case DataScopeEnum::TENANT:
                $this->applyTenantScope($builder, $model, $user);
                break;

            case DataScopeEnum::DEPARTMENT:
                $this->applyDeptScope($builder, $model, $user);
                break;

            case DataScopeEnum::DEPARTMENT_AND_SUB:
                $this->applyDeptAndSubScope($builder, $model, $user);
                break;

            case DataScopeEnum::CUSTOM:
                $this->applyCustomScope($builder, $model, $user);
                break;

            case DataScopeEnum::SELF:
            default:
                $this->applySelfScope($builder, $model, $user);
                break;
        }
    }

    protected function applyAllScope(Builder $builder, Model $model, $user): void
    {
    }

    protected function applyTenantScope(Builder $builder, Model $model, $user): void
    {
        $tenantId = $user->tenant_id;
        if ($tenantId) {
            $builder->where($model->qualifyColumn($this->config['tenant_id_column']), $tenantId);
        }
    }

    protected function applyDeptScope(Builder $builder, Model $model, $user): void
    {
        $deptId = $user->dept_id;
        if ($deptId) {
            $builder->where($model->qualifyColumn($this->config['dept_id_column']), $deptId);
        } else {
            $this->applySelfScope($builder, $model, $user);
        }
    }

    protected function applyDeptAndSubScope(Builder $builder, Model $model, $user): void
    {
        $deptId = $user->dept_id;
        if (!$deptId) {
            $this->applySelfScope($builder, $model, $user);
            return;
        }

        $subDeptIds = $this->getSubDeptIds($deptId);
        $allDeptIds = array_merge([$deptId], $subDeptIds);

        $builder->where(function (Builder $query) use ($model, $allDeptIds, $user) {
            $query->whereIn($model->qualifyColumn($this->config['dept_id_column']), $allDeptIds);
            if ($user->id) {
                $query->orWhere($model->qualifyColumn($this->config['user_id_column']), $user->id);
            }
        });
    }

    protected function applySelfScope(Builder $builder, Model $model, $user): void
    {
        $builder->where($model->qualifyColumn($this->config['user_id_column']), $user->id);
    }

    protected function applyCustomScope(Builder $builder, Model $model, $user): void
    {
        $deptIds = $this->getCustomDeptIds($user);

        if (empty($deptIds)) {
            $this->applySelfScope($builder, $model, $user);
            return;
        }

        $builder->where(function (Builder $query) use ($model, $deptIds, $user) {
            $query->whereIn($model->qualifyColumn($this->config['dept_id_column']), $deptIds);
            if ($user->id) {
                $query->orWhere($model->qualifyColumn($this->config['user_id_column']), $user->id);
            }
        });
    }

    protected function getSubDeptIds(int $parentDeptId): array
    {
        try {
            $depts = DB::table('sys_dept')
                ->select('id', 'parent_id')
                ->get();

            $result = [];
            $this->collectChildDeptIds($depts, $parentDeptId, $result);
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function collectChildDeptIds($depts, int $parentId, array &$result): void
    {
        foreach ($depts as $dept) {
            if ($dept->parent_id == $parentId) {
                $result[] = $dept->id;
                $this->collectChildDeptIds($depts, $dept->id, $result);
            }
        }
    }

    protected function getCustomDeptIds($user): array
    {
        try {
            if (isset($user->id)) {
                return DB::table('sys_role_dept')
                    ->join('sys_user_role', 'sys_role_dept.role_id', '=', 'sys_user_role.role_id')
                    ->where('sys_user_role.user_id', $user->id)
                    ->pluck('sys_role_dept.dept_id')
                    ->toArray();
            }
        } catch (\Exception $e) {
        }
        return [];
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutDataScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('withDataScope', function (Builder $builder, $config = []) {
            return $builder->withGlobalScope(get_class($this), new self($config));
        });
    }
}
