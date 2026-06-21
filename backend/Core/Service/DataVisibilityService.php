<?php

namespace App\Core\Service;

use App\Core\Context\TenantContext;
use App\Core\Enum\DataScopeLevel;
use App\Core\Enum\RoleType;

class DataVisibilityService
{
    private TenantContext $context;

    public function __construct()
    {
        $this->context = TenantContext::getInstance();
    }

    public function getAvailableScopes(): array
    {
        $role = $this->context->getRole();
        $default = $role?->defaultDataScope() ?? DataScopeLevel::SELF;

        $allScopes = [
            DataScopeLevel::ALL,
            DataScopeLevel::TENANT,
            DataScopeLevel::DEPARTMENT,
            DataScopeLevel::TEAM,
            DataScopeLevel::SELF,
        ];

        return array_values(array_filter(
            $allScopes,
            fn(DataScopeLevel $s) => $default->gte($s)
        ));
    }

    public function switchScope(DataScopeLevel $target): array
    {
        $available = $this->getAvailableScopes();
        $allowed = false;
        foreach ($available as $s) {
            if ($s->value === $target->value) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException('无权切换到该数据可见范围: ' . $target->label());
        }

        $this->context->setDataScope($target);

        return [
            'current_scope' => $target->value,
            'current_scope_label' => $target->label(),
            'available_scopes' => array_map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], $available),
        ];
    }

    public function canViewResource(array $resource): bool
    {
        $scope = $this->context->getDataScope();

        switch ($scope) {
            case DataScopeLevel::ALL:
                return true;

            case DataScopeLevel::TENANT:
                $tenantId = $this->context->getTenantId();
                return $tenantId === null || ($resource['tenant_id'] ?? null) == $tenantId;

            case DataScopeLevel::DEPARTMENT:
                $deptIds = $this->context->getDeptChildIds();
                if (empty($deptIds)) {
                    $deptId = $this->context->getDeptId();
                    return $deptId !== null && ($resource['dept_id'] ?? null) == $deptId;
                }
                return in_array($resource['dept_id'] ?? null, $deptIds, true);

            case DataScopeLevel::TEAM:
                $memberIds = $this->context->getTeamMemberIds();
                $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
                if (empty($memberIds)) {
                    return $ownerId == $this->context->getUserId();
                }
                return in_array($ownerId, $memberIds, true);

            case DataScopeLevel::SELF:
            default:
                $userId = $this->context->getUserId();
                return ($resource['owner_id'] ?? null) == $userId
                    || ($resource['created_by'] ?? null) == $userId
                    || ($resource['user_id'] ?? null) == $userId;
        }
    }

    public function canModifyResource(array $resource): bool
    {
        if (!$this->canViewResource($resource)) {
            return false;
        }

        $userId = $this->context->getUserId();
        $role = $this->context->getRole();

        if ($this->context->isSuperAdmin() || $role === RoleType::TENANT_ADMIN) {
            return true;
        }

        if ($role === RoleType::DEPT_HEAD) {
            return true;
        }

        if ($role === RoleType::TEAM_LEADER) {
            $memberIds = $this->context->getTeamMemberIds();
            $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
            return in_array($ownerId, $memberIds, true) || $ownerId == $userId;
        }

        return ($resource['owner_id'] ?? null) == $userId
            || ($resource['created_by'] ?? null) == $userId;
    }

    public function assertCanView(array $resource, string $resourceName = '资源'): void
    {
        if (!$this->canViewResource($resource)) {
            throw new \RuntimeException("无权查看该{$resourceName}");
        }
    }

    public function assertCanModify(array $resource, string $resourceName = '资源'): void
    {
        if (!$this->canModifyResource($resource)) {
            throw new \RuntimeException("无权修改该{$resourceName}");
        }
    }

    public function getScopeSummary(): array
    {
        $ctx = $this->context;
        return [
            'tenant' => [
                'id' => $ctx->getTenantId(),
                'mode' => $ctx->getTenantId() === null && $ctx->isSuperAdmin() ? 'all_tenants' : 'single_tenant',
            ],
            'user' => [
                'id' => $ctx->getUserId(),
                'username' => $ctx->getUsername(),
                'role' => $ctx->getRole()?->value,
                'role_label' => $ctx->getRole()?->label(),
            ],
            'data_scope' => [
                'current' => $ctx->getDataScope()->value,
                'current_label' => $ctx->getDataScope()->label(),
                'available' => array_map(fn($s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ], $this->getAvailableScopes()),
            ],
            'org' => [
                'dept_id' => $ctx->getDeptId(),
                'dept_child_ids' => $ctx->getDeptChildIds(),
                'team_id' => $ctx->getTeamId(),
                'team_member_ids' => $ctx->getTeamMemberIds(),
            ],
        ];
    }

    public function buildCrossRoleFilter(array $targetRoles = []): array
    {
        $role = $this->context->getRole();
        $roleHierarchy = [
            RoleType::SUPER_ADMIN->value => 100,
            RoleType::TENANT_ADMIN->value => 80,
            RoleType::DEPT_HEAD->value => 60,
            RoleType::TEAM_LEADER->value => 40,
            RoleType::TEACHER->value => 20,
            RoleType::STUDENT->value => 10,
        ];

        $currentLevel = $roleHierarchy[$role?->value ?? RoleType::STUDENT->value];

        if (empty($targetRoles)) {
            $visibleRoles = array_filter(
                $roleHierarchy,
                fn($level) => $level <= $currentLevel,
                ARRAY_FILTER_USE_BOTH
            );
            return array_keys($visibleRoles);
        }

        $result = [];
        foreach ($targetRoles as $tr) {
            $trValue = is_string($tr) ? $tr : $tr->value;
            if (isset($roleHierarchy[$trValue]) && $roleHierarchy[$trValue] <= $currentLevel) {
                $result[] = $trValue;
            }
        }

        return $result;
    }
}
