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

    protected static $appliedWarnings = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'user_id_column' => 'created_by',
            'dept_id_column' => 'dept_id',
            'tenant_id_column' => 'tenant_id',
            'resource' => null,
        ], $config);
    }

    public function apply(Builder $builder, Model $model)
    {
        if (TenantContext::isSuperAdmin()) {
            self::recordScopeApplied($model, DataScopeEnum::ALL, '超级管理员，跳过过滤');
            return;
        }

        $user = TenantContext::getUser();
        if (empty($user)) {
            self::recordScopeApplied($model, null, '用户未登录，跳过过滤');
            return;
        }

        $declaredScope = $user->data_scope ?? DataScopeEnum::SELF;
        $actualScope = $declaredScope;
        $downgradeReason = null;

        switch ($declaredScope) {
            case DataScopeEnum::ALL:
                $this->applyAllScope($builder, $model, $user);
                break;

            case DataScopeEnum::TENANT:
                $actualScope = $this->applyTenantScopeWithValidation($builder, $model, $user, $declaredScope);
                if ($actualScope !== $declaredScope) {
                    $downgradeReason = "租户信息不完整";
                }
                break;

            case DataScopeEnum::DEPARTMENT:
                $actualScope = $this->applyDeptScopeWithValidation($builder, $model, $user, $declaredScope);
                if ($actualScope !== $declaredScope) {
                    $downgradeReason = "未分配部门";
                }
                break;

            case DataScopeEnum::DEPARTMENT_AND_SUB:
                $actualScope = $this->applyDeptAndSubScopeWithValidation($builder, $model, $user, $declaredScope);
                if ($actualScope !== $declaredScope) {
                    $downgradeReason = "部门数据不完整";
                }
                break;

            case DataScopeEnum::CUSTOM:
                $actualScope = $this->applyCustomScopeWithValidation($builder, $model, $user, $declaredScope);
                if ($actualScope !== $declaredScope) {
                    $downgradeReason = "自定义部门为空";
                }
                break;

            case DataScopeEnum::SELF:
            default:
                $actualScope = DataScopeEnum::SELF;
                $this->applySelfScope($builder, $model, $user);
                break;
        }

        if ($downgradeReason) {
            self::recordScopeApplied(
                $model,
                $actualScope,
                sprintf(
                    '范围降级：%s → %s（%s）',
                    DataScopeEnum::label($declaredScope),
                    DataScopeEnum::label($actualScope),
                    $downgradeReason
                ),
                [
                    'from' => $declaredScope,
                    'to' => $actualScope,
                    'reason' => $downgradeReason,
                ]
            );
        } else {
            self::recordScopeApplied($model, $actualScope, DataScopeEnum::label($actualScope));
        }
    }

    protected function applyAllScope(Builder $builder, Model $model, $user): void
    {
    }

    protected function applyTenantScopeWithValidation(Builder $builder, Model $model, $user, int $declaredScope): int
    {
        $tenantId = $user->tenant_id ?? null;
        if ($tenantId) {
            $builder->where($model->qualifyColumn($this->config['tenant_id_column']), $tenantId);
            return $declaredScope;
        }
        $this->applySelfScope($builder, $model, $user);
        return DataScopeEnum::SELF;
    }

    protected function applyDeptScopeWithValidation(Builder $builder, Model $model, $user, int $declaredScope): int
    {
        $deptId = $user->dept_id ?? null;
        if ($deptId) {
            $builder->where($model->qualifyColumn($this->config['dept_id_column']), $deptId);
            return $declaredScope;
        }
        $this->applySelfScope($builder, $model, $user);
        return DataScopeEnum::SELF;
    }

    protected function applyDeptAndSubScopeWithValidation(Builder $builder, Model $model, $user, int $declaredScope): int
    {
        $deptId = $user->dept_id ?? null;
        if (!$deptId) {
            $this->applySelfScope($builder, $model, $user);
            return DataScopeEnum::SELF;
        }

        $subDeptIds = $this->getSubDeptIds($deptId);
        if (empty($subDeptIds)) {
            $builder->where(function (Builder $query) use ($model, $deptId, $user) {
                $query->where($model->qualifyColumn($this->config['dept_id_column']), $deptId);
                if (!empty($user->id)) {
                    $query->orWhere($model->qualifyColumn($this->config['user_id_column']), $user->id);
                }
            });
            return DataScopeEnum::DEPARTMENT;
        }

        $allDeptIds = array_merge([$deptId], $subDeptIds);
        $builder->where(function (Builder $query) use ($model, $allDeptIds, $user) {
            $query->whereIn($model->qualifyColumn($this->config['dept_id_column']), $allDeptIds);
            if (!empty($user->id)) {
                $query->orWhere($model->qualifyColumn($this->config['user_id_column']), $user->id);
            }
        });
        return $declaredScope;
    }

    protected function applyCustomScopeWithValidation(Builder $builder, Model $model, $user, int $declaredScope): int
    {
        $deptIds = $this->getCustomDeptIds($user);
        if (empty($deptIds)) {
            $this->applySelfScope($builder, $model, $user);
            return DataScopeEnum::SELF;
        }

        $builder->where(function (Builder $query) use ($model, $deptIds, $user) {
            $query->whereIn($model->qualifyColumn($this->config['dept_id_column']), $deptIds);
            if (!empty($user->id)) {
                $query->orWhere($model->qualifyColumn($this->config['user_id_column']), $user->id);
            }
        });
        return $declaredScope;
    }

    protected function applySelfScope(Builder $builder, Model $model, $user): void
    {
        $builder->where($model->qualifyColumn($this->config['user_id_column']), $user->id);
    }

    protected function getSubDeptIds(int $parentDeptId): array
    {
        try {
            $depts = DB::table('sys_dept')
                ->select('id', 'parent_id')
                ->where('tenant_id', TenantContext::getTenantId())
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

    public static function recordScopeApplied(Model $model, ?int $scope, string $note, array $extra = []): void
    {
        $key = get_class($model);
        self::$appliedWarnings[$key] = [
            'scope' => $scope,
            'scope_label' => $scope !== null ? DataScopeEnum::label($scope) : '未设置',
            'note' => $note,
            'extra' => $extra,
            'applied_at' => microtime(true),
        ];
    }

    public static function flushAppliedWarnings(): array
    {
        $warnings = self::$appliedWarnings;
        self::$appliedWarnings = [];
        return $warnings;
    }

    public static function hasWarnings(): bool
    {
        foreach (self::$appliedWarnings as $info) {
            if (!empty($info['extra']['from']) && $info['extra']['from'] !== $info['extra']['to']) {
                return true;
            }
        }
        return false;
    }
}
