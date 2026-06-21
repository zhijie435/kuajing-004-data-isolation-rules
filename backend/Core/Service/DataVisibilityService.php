<?php

namespace App\Core\Service;

use App\Core\Context\TenantContext;
use App\Core\Enum\DataScopeLevel;
use App\Core\Enum\RoleType;
use App\Core\Exception\ForbiddenException;

class DataVisibilityService
{
    private TenantContext $context;

    public const ROLE_HIERARCHY = [
        RoleType::SUPER_ADMIN->value => 100,
        RoleType::TENANT_ADMIN->value => 80,
        RoleType::DEPT_HEAD->value => 60,
        RoleType::TEAM_LEADER->value => 40,
        RoleType::TEACHER->value => 20,
        RoleType::STUDENT->value => 10,
    ];

    public const OWNER_ID_ROLE_MAP = [
        999 => RoleType::SUPER_ADMIN->value,
        101 => RoleType::TENANT_ADMIN->value,
        102 => RoleType::DEPT_HEAD->value,
        201 => RoleType::TEAM_LEADER->value,
        202 => RoleType::TEACHER->value,
        203 => RoleType::TEACHER->value,
        204 => RoleType::TEACHER->value,
        301 => RoleType::TEACHER->value,
        302 => RoleType::TEACHER->value,
        303 => RoleType::TEACHER->value,
        401 => RoleType::TENANT_ADMIN->value,
        402 => RoleType::DEPT_HEAD->value,
        501 => RoleType::STUDENT->value,
    ];

    public function __construct()
    {
        $this->context = TenantContext::getInstance();
    }

    public function getOwnerRoleById(?int $ownerId): string
    {
        if ($ownerId === null) return RoleType::STUDENT->value;
        return self::OWNER_ID_ROLE_MAP[$ownerId] ?? RoleType::STUDENT->value;
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
        $allowed = in_array($target->value, array_column($available, 'value'), true);

        if (!$allowed) {
            throw (new ForbiddenException('无权切换到该数据可见范围：' . $target->label()))
                ->setContext([
                    'target_scope' => $target->value,
                    'available_scopes' => array_map(fn($s) => $s->value, $available),
                ]);
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
        return $this->checkVisibility($resource, $this->context->getDataScope());
    }

    public function canModifyResource(array $resource): bool
    {
        if (!$this->canViewResource($resource)) {
            return false;
        }

        $userId = $this->context->getUserId();
        $role = $this->context->getRole();

        return match (true) {
            $this->context->isSuperAdmin() || $role === RoleType::TENANT_ADMIN => true,
            $role === RoleType::DEPT_HEAD => true,
            $role === RoleType::TEAM_LEADER => $this->isTeamMemberOrSelf($resource, $userId),
            default => $this->isOwnerOrCreator($resource, $userId),
        };
    }

    private function checkVisibility(array $resource, DataScopeLevel $scope): bool
    {
        return match ($scope) {
            DataScopeLevel::ALL => true,
            DataScopeLevel::TENANT => $this->checkTenantScope($resource),
            DataScopeLevel::DEPARTMENT => $this->checkDeptScope($resource),
            DataScopeLevel::TEAM => $this->checkTeamScope($resource),
            DataScopeLevel::SELF => $this->checkSelfScope($resource),
        };
    }

    private function checkTenantScope(array $resource): bool
    {
        $tenantId = $this->context->getTenantId();
        return $tenantId === null || ($resource['tenant_id'] ?? null) == $tenantId;
    }

    private function checkDeptScope(array $resource): bool
    {
        $deptIds = $this->context->getDeptChildIds();
        $resourceDeptId = $resource['dept_id'] ?? null;

        if (empty($deptIds)) {
            $deptId = $this->context->getDeptId();
            return $deptId !== null && $resourceDeptId == $deptId;
        }

        return in_array($resourceDeptId, $deptIds, true);
    }

    private function checkTeamScope(array $resource): bool
    {
        $memberIds = $this->context->getTeamMemberIds();
        $ownerId = $this->getResourceOwnerId($resource);

        if (empty($memberIds)) {
            return $ownerId == $this->context->getUserId();
        }

        return in_array($ownerId, $memberIds, true);
    }

    private function checkSelfScope(array $resource): bool
    {
        $userId = $this->context->getUserId();
        return $this->isOwnerOrCreator($resource, $userId);
    }

    private function getResourceOwnerId(array $resource): ?int
    {
        return $resource['owner_id'] ?? $resource['created_by'] ?? null;
    }

    private function isOwnerOrCreator(array $resource, int $userId): bool
    {
        return ($resource['owner_id'] ?? null) == $userId
            || ($resource['created_by'] ?? null) == $userId
            || ($resource['user_id'] ?? null) == $userId;
    }

    private function isTeamMemberOrSelf(array $resource, int $userId): bool
    {
        $memberIds = $this->context->getTeamMemberIds();
        $ownerId = $this->getResourceOwnerId($resource);
        return in_array($ownerId, $memberIds, true) || $ownerId == $userId;
    }

    public function assertCanView(array $resource, string $resourceName = '资源'): void
    {
        if (!$this->canViewResource($resource)) {
            $ctx = $this->context;
            $scope = $ctx->getDataScope();
            $reason = $this->explainVisibilityDenial($resource, $scope);
            throw (new ForbiddenException("无权查看该{$resourceName}：{$reason}"))
                ->setContext([
                    'resource_id' => $resource['id'] ?? null,
                    'resource_owner_id' => $resource['owner_id'] ?? $resource['created_by'] ?? null,
                    'resource_tenant_id' => $resource['tenant_id'] ?? null,
                    'resource_dept_id' => $resource['dept_id'] ?? null,
                    'current_user_id' => $ctx->getUserId(),
                    'current_role' => $ctx->getRole()?->value,
                    'current_role_label' => $ctx->getRole()?->label(),
                    'current_scope' => $scope->value,
                    'current_scope_label' => $scope->label(),
                    'current_tenant_id' => $ctx->getTenantId(),
                    'current_dept_id' => $ctx->getDeptId(),
                    'denial_reason' => $reason,
                ]);
        }
    }

    public function assertCanModify(array $resource, string $resourceName = '资源'): void
    {
        $ctx = $this->context;
        $scope = $ctx->getDataScope();

        if (!$this->canViewResource($resource)) {
            $reason = $this->explainVisibilityDenial($resource, $scope);
            throw (new ForbiddenException("无权修改该{$resourceName}：{$reason}"))
                ->setContext([
                    'resource_id' => $resource['id'] ?? null,
                    'resource_owner_id' => $resource['owner_id'] ?? $resource['created_by'] ?? null,
                    'resource_tenant_id' => $resource['tenant_id'] ?? null,
                    'current_user_id' => $ctx->getUserId(),
                    'current_role' => $ctx->getRole()?->value,
                    'current_role_label' => $ctx->getRole()?->label(),
                    'current_scope' => $scope->value,
                    'current_scope_label' => $scope->label(),
                    'denial_reason' => 'resource_not_visible',
                    'detail' => $reason,
                ]);
        }

        if (!$this->canModifyResource($resource)) {
            $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
            $role = $ctx->getRole();
            $reason = '需为资源负责人或具备管理权限';
            if ($role === RoleType::TEAM_LEADER) {
                $reason = '团队负责人仅可修改本团队成员的资源';
            } elseif ($role === RoleType::TEACHER || $role === RoleType::STUDENT) {
                $reason = '仅可修改自己创建或负责的资源';
            }
            throw (new ForbiddenException("无权修改该{$resourceName}：{$reason}"))
                ->setContext([
                    'resource_id' => $resource['id'] ?? null,
                    'resource_owner_id' => $ownerId,
                    'current_user_id' => $ctx->getUserId(),
                    'current_role' => $role?->value,
                    'current_role_label' => $role?->label(),
                    'current_scope' => $scope->value,
                    'current_scope_label' => $scope->label(),
                    'denial_reason' => 'not_owner_or_admin',
                    'detail' => $reason,
                ]);
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
        $roleHierarchy = self::ROLE_HIERARCHY;

        $currentLevel = $roleHierarchy[$role?->value ?? RoleType::STUDENT->value];

        $visibleRoles = [];
        foreach ($roleHierarchy as $roleKey => $level) {
            if ($level <= $currentLevel) {
                $visibleRoles[] = $roleKey;
            }
        }

        if (empty($targetRoles)) {
            return $visibleRoles;
        }

        $result = [];
        foreach ($targetRoles as $tr) {
            $trValue = is_string($tr) ? $tr : $tr->value;
            if (in_array($trValue, $visibleRoles, true)) {
                $result[] = $trValue;
            }
        }

        return array_values(array_unique($result));
    }

    public function filterResourcesByRoles(array $resources, array $targetRoles = []): array
    {
        $visibleRoles = $this->buildCrossRoleFilter($targetRoles);
        $result = [];

        foreach ($resources as $resource) {
            $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
            $ownerRole = $this->getOwnerRoleById($ownerId);

            if (in_array($ownerRole, $visibleRoles, true)) {
                $result[] = [
                    'id' => $resource['id'] ?? null,
                    'title' => $resource['title'] ?? null,
                    'owner_id' => $ownerId,
                    'owner_role' => $ownerRole,
                    'owner_role_label' => RoleType::from($ownerRole)->label(),
                    'tenant_id' => $resource['tenant_id'] ?? null,
                ];
            }
        }

        return $result;
    }

    public function exportCrossRoleAudit(array $resources, string $resourceType = 'resource'): array
    {
        $ctx = $this->context;
        $currentRole = $ctx->getRole();
        $currentScope = $ctx->getDataScope();

        $roleScopeExpectation = $this->buildRoleScopeExpectation();
        $roleVisibilityMap = $this->buildRoleVisibilityMap($roleScopeExpectation);
        $currentExpectation = $roleScopeExpectation[$currentRole?->value ?? RoleType::STUDENT->value] ?? null;
        $scopeMismatch = $this->checkScopeMismatch($currentScope, $currentExpectation);

        $auditedResources = [];
        $anomalies = [];
        $errorCount = 0;
        $warningCount = 0;

        foreach ($resources as $resource) {
            $auditResult = $this->auditSingleResource($resource, $currentRole, $currentScope, $resourceType);
            $auditedResources[] = $auditResult['resource'];
            $anomalies = array_merge($anomalies, $auditResult['anomalies']);
            $errorCount += $auditResult['error_count'];
            $warningCount += $auditResult['warning_count'];
        }

        $summary = $this->buildAuditSummary($auditedResources, $anomalies, $errorCount, $warningCount, $scopeMismatch);
        $contextSnapshot = $this->buildContextSnapshot($currentRole, $currentScope, $scopeMismatch);

        if ($scopeMismatch && $currentExpectation) {
            $anomalies[] = $this->buildScopeMismatchAnomaly($currentScope, $currentExpectation, $resourceType);
            $this->updateSummaryForScopeMismatch($summary);
        }

        return [
            'summary' => $summary,
            'context' => $contextSnapshot,
            'role_visibility_export' => array_values($roleVisibilityMap),
            'audited_resources' => $auditedResources,
            'anomalies' => $anomalies,
        ];
    }

    private function buildRoleScopeExpectation(): array
    {
        $expectations = [];
        foreach (RoleType::cases() as $role) {
            $scope = $role->defaultDataScope();
            $expectations[$role->value] = [
                'scope' => $scope,
                'scope_label' => $scope->label(),
                'expected_visibility' => $this->getScopeDescription($role, $scope),
            ];
        }
        return $expectations;
    }

    private function getScopeDescription(RoleType $role, DataScopeLevel $scope): string
    {
        return match ($role) {
            RoleType::SUPER_ADMIN => '可查看所有租户所有数据',
            RoleType::TENANT_ADMIN => '可查看本租户全部数据',
            RoleType::DEPT_HEAD => '可查看本部门及下级部门数据',
            RoleType::TEAM_LEADER => '可查看本团队成员数据',
            RoleType::TEACHER, RoleType::STUDENT => '仅可查看本人创建/负责的数据',
        };
    }

    private function buildRoleVisibilityMap(array $roleScopeExpectation): array
    {
        $map = [];
        foreach (RoleType::cases() as $rt) {
            $map[$rt->value] = [
                'role' => $rt->value,
                'role_label' => $rt->label(),
                'default_scope' => $rt->defaultDataScope()->value,
                'default_scope_label' => $rt->defaultDataScope()->label(),
                'expected_visibility' => $roleScopeExpectation[$rt->value]['expected_visibility'],
            ];
        }
        return $map;
    }

    private function checkScopeMismatch(DataScopeLevel $currentScope, ?array $expectation): bool
    {
        return $expectation !== null && $currentScope->value !== $expectation['scope']->value;
    }

    private function auditSingleResource(array $resource, ?RoleType $currentRole, DataScopeLevel $currentScope, string $resourceType): array
    {
        $actualVisible = $this->canViewResource($resource);
        $actualModifiable = $this->canModifyResource($resource);
        $expectedVisible = $this->computeExpectedVisibility($resource, $currentRole, $currentScope);
        $ownerId = $this->getResourceOwnerId($resource);
        $resourceDeptId = $resource['dept_id'] ?? null;

        $anomalies = [];
        $errorCount = 0;
        $warningCount = 0;

        $visibilityAnomaly = $this->checkVisibilityMismatch($resource, $actualVisible, $expectedVisible, $resourceType);
        if ($visibilityAnomaly) {
            $anomalies[] = $visibilityAnomaly;
            $errorCount++;
        }

        $crossTenantAnomaly = $this->checkCrossTenantLeak($resource, $actualVisible, $currentRole, $currentScope, $resourceType);
        if ($crossTenantAnomaly) {
            $anomalies[] = $crossTenantAnomaly;
            $errorCount++;
        }

        $modifyAnomaly = $this->checkModifyWithoutView($resource, $actualVisible, $actualModifiable, $resourceType);
        if ($modifyAnomaly) {
            $anomalies[] = $modifyAnomaly;
            $warningCount++;
        }

        $deptAnomaly = $this->checkDeptScopeOverflow($resource, $actualVisible, $currentRole, $resourceDeptId, $resourceType);
        if ($deptAnomaly) {
            $anomalies[] = $deptAnomaly;
            $warningCount++;
        }

        return [
            'resource' => [
                'id' => $resource['id'] ?? null,
                'title' => $resource['title'] ?? ($resource['name'] ?? null),
                'owner_id' => $ownerId,
                'tenant_id' => $resource['tenant_id'] ?? null,
                'dept_id' => $resourceDeptId,
                'actual_visible' => $actualVisible,
                'expected_visible' => $expectedVisible,
                'actual_modifiable' => $actualModifiable,
                'anomaly' => $visibilityAnomaly ? $visibilityAnomaly['type'] : null,
                'cross_tenant_leak' => $crossTenantAnomaly !== null,
                'modify_without_view' => $modifyAnomaly !== null,
                'dept_scope_overflow' => $deptAnomaly !== null,
            ],
            'anomalies' => $anomalies,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
        ];
    }

    private function checkVisibilityMismatch(array $resource, bool $actualVisible, bool $expectedVisible, string $resourceType): ?array
    {
        if ($actualVisible === $expectedVisible) {
            return null;
        }

        $type = $actualVisible ? 'VISIBLE_MISMATCH_ACTUAL_VISIBLE' : 'VISIBLE_MISMATCH_ACTUAL_HIDDEN';
        $detail = $actualVisible
            ? "资源#{$resource['id']}实际可见但按规则不应可见（数据越权泄露风险）"
            : "资源#{$resource['id']}按规则应可见但实际不可见（数据可见性缺失）";

        return [
            'type' => $type,
            'severity' => 'error',
            'resource_id' => $resource['id'] ?? null,
            'resource_type' => $resourceType,
            'detail' => $detail,
            'expected' => $expectedVisible,
            'actual' => $actualVisible,
            'resource_owner_id' => $this->getResourceOwnerId($resource),
            'resource_tenant_id' => $resource['tenant_id'] ?? null,
        ];
    }

    private function checkCrossTenantLeak(array $resource, bool $actualVisible, ?RoleType $currentRole, DataScopeLevel $currentScope, string $resourceType): ?array
    {
        if (!$actualVisible || $currentRole === RoleType::SUPER_ADMIN || $currentScope === DataScopeLevel::ALL) {
            return null;
        }

        $ctx = $this->context;
        $resourceTenantId = $resource['tenant_id'] ?? null;
        $currentTenantId = $ctx->getTenantId();

        if ($resourceTenantId === null || $currentTenantId === null || $resourceTenantId == $currentTenantId) {
            return null;
        }

        return [
            'type' => 'CROSS_TENANT_LEAK',
            'severity' => 'error',
            'resource_id' => $resource['id'] ?? null,
            'resource_type' => $resourceType,
            'detail' => "资源#{$resource['id']}属于租户{$resourceTenantId}，但当前用户租户为{$currentTenantId}，存在跨租户数据泄露",
            'resource_tenant_id' => $resourceTenantId,
            'current_tenant_id' => $currentTenantId,
        ];
    }

    private function checkModifyWithoutView(array $resource, bool $actualVisible, bool $actualModifiable, string $resourceType): ?array
    {
        if (!$actualModifiable || $actualVisible) {
            return null;
        }

        return [
            'type' => 'MODIFY_WITHOUT_VIEW',
            'severity' => 'warning',
            'resource_id' => $resource['id'] ?? null,
            'resource_type' => $resourceType,
            'detail' => "资源#{$resource['id']}可修改但不可查看，权限配置可能异常",
        ];
    }

    private function checkDeptScopeOverflow(array $resource, bool $actualVisible, ?RoleType $currentRole, ?int $resourceDeptId, string $resourceType): ?array
    {
        if (!$actualVisible || $currentRole !== RoleType::DEPT_HEAD || $resourceDeptId === null) {
            return null;
        }

        $deptChildIds = $this->context->getDeptChildIds();
        if (empty($deptChildIds) || in_array($resourceDeptId, $deptChildIds, true)) {
            return null;
        }

        return [
            'type' => 'DEPT_SCOPE_OVERFLOW',
            'severity' => 'warning',
            'resource_id' => $resource['id'] ?? null,
            'resource_type' => $resourceType,
            'detail' => "资源#{$resource['id']}所属部门{$resourceDeptId}不在当前用户管辖范围" . json_encode($deptChildIds) . "内，但数据可见",
            'resource_dept_id' => $resourceDeptId,
            'managed_dept_ids' => $deptChildIds,
        ];
    }

    private function buildAuditSummary(array $auditedResources, array $anomalies, int $errorCount, int $warningCount, bool $scopeMismatch): array
    {
        $totalResources = count($auditedResources);
        $actualVisibleCount = count(array_filter($auditedResources, fn($r) => $r['actual_visible']));
        $expectedVisibleCount = count(array_filter($auditedResources, fn($r) => $r['expected_visible']));
        $anomalyCount = count($anomalies);

        $overallStatus = $this->determineOverallStatus($errorCount, $warningCount, $scopeMismatch);

        return [
            'total_resources' => $totalResources,
            'actual_visible_count' => $actualVisibleCount,
            'expected_visible_count' => $expectedVisibleCount,
            'visible_count_match' => $actualVisibleCount === $expectedVisibleCount,
            'anomaly_count' => $anomalyCount,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'overall_status' => $overallStatus,
        ];
    }

    private function determineOverallStatus(int $errorCount, int $warningCount, bool $scopeMismatch): string
    {
        if ($errorCount > 0) {
            return 'error';
        }
        if ($warningCount > 0 || $scopeMismatch) {
            return 'warning';
        }
        return 'healthy';
    }

    private function buildContextSnapshot(?RoleType $currentRole, DataScopeLevel $currentScope, bool $scopeMismatch): array
    {
        $ctx = $this->context;
        return [
            'user_id' => $ctx->getUserId(),
            'username' => $ctx->getUsername(),
            'role' => $currentRole?->value,
            'role_label' => $currentRole?->label(),
            'current_scope' => $currentScope->value,
            'current_scope_label' => $currentScope->label(),
            'tenant_id' => $ctx->getTenantId(),
            'dept_id' => $ctx->getDeptId(),
            'scope_mismatch' => $scopeMismatch,
        ];
    }

    private function buildScopeMismatchAnomaly(DataScopeLevel $currentScope, array $expectation, string $resourceType): array
    {
        return [
            'type' => 'SCOPE_MISMATCH',
            'severity' => 'warning',
            'resource_id' => null,
            'resource_type' => $resourceType,
            'detail' => "当前数据可见范围为「{$currentScope->label()}」，但角色默认范围为「{$expectation['scope']->label()}」，可能存在越权或范围缩窄",
            'current_scope' => $currentScope->value,
            'default_scope' => $expectation['scope']->value,
        ];
    }

    private function updateSummaryForScopeMismatch(array &$summary): void
    {
        $summary['warning_count']++;
        $summary['anomaly_count']++;
        if ($summary['overall_status'] === 'healthy') {
            $summary['overall_status'] = 'warning';
        }
    }

    private function computeExpectedVisibility(array $resource, ?RoleType $role, DataScopeLevel $scope): bool
    {
        if ($role === null) {
            return false;
        }

        $defaultScope = $role->defaultDataScope();
        return $this->checkVisibility($resource, $defaultScope);
    }

    private function explainVisibilityDenial(array $resource, DataScopeLevel $scope): string
    {
        $ctx = $this->context;

        return match ($scope) {
            DataScopeLevel::ALL => '当前可见范围为全部数据但仍无法查看，请检查数据完整性',
            DataScopeLevel::TENANT => '资源不属于当前租户',
            DataScopeLevel::DEPARTMENT => '资源不属于当前用户管辖的部门范围',
            DataScopeLevel::TEAM => '资源负责人不在当前团队成员中',
            DataScopeLevel::SELF => '资源非当前用户创建或负责',
            default => '当前数据可见范围为「' . $scope->label() . '」，无法查看该资源',
        };
    }

    private function cloneContextSnapshot(): array
    {
        return [
            'tenant_id' => $this->context->getTenantId(),
            'user_id' => $this->context->getUserId(),
            'username' => $this->context->getUsername(),
            'role' => $this->context->getRole()?->value,
            'data_scope' => $this->context->getDataScope()->value,
            'dept_id' => $this->context->getDeptId(),
            'team_id' => $this->context->getTeamId(),
        ];
    }

    public function applyAuditWriteBack(): array
    {
        $ctx = $this->context;
        $role = $ctx->getRole();
        $currentScope = $ctx->getDataScope();

        if ($role === null) {
            return [
                'corrected' => false,
                'reason' => 'no_role',
                'message' => '当前无角色信息，无法回写',
            ];
        }

        $defaultScope = $role->defaultDataScope();

        if ($currentScope->value === $defaultScope->value) {
            return [
                'corrected' => false,
                'reason' => 'already_correct',
                'message' => '当前可见范围与角色默认范围一致，无需回写',
                'current_scope' => $currentScope->value,
                'current_scope_label' => $currentScope->label(),
                'default_scope' => $defaultScope->value,
                'default_scope_label' => $defaultScope->label(),
            ];
        }

        $previousScope = $currentScope;
        $ctx->writeBackScope($defaultScope);

        return [
            'corrected' => true,
            'reason' => 'scope_mismatch_fixed',
            'message' => "已将可见范围从「{$previousScope->label()}」回写修正为「{$defaultScope->label()}」",
            'previous_scope' => $previousScope->value,
            'previous_scope_label' => $previousScope->label(),
            'corrected_scope' => $defaultScope->value,
            'corrected_scope_label' => $defaultScope->label(),
            'role' => $role->value,
            'role_label' => $role->label(),
        ];
    }

    public function applyCrossRoleAuditFix(array $auditResult): array
    {
        $ctx = $this->context;
        $fixes = [];
        $scopeFix = null;

        foreach ($auditResult['anomalies'] ?? [] as $anomaly) {
            $fix = $this->processAnomaly($anomaly, $scopeFix);
            if ($fix['type'] === 'SCOPE_MISMATCH') {
                $scopeFix = $fix['result'];
            }
            $fixes[] = $fix;
        }

        $reAuditSummary = $this->runReAuditIfNeeded($scopeFix, $auditResult);

        return [
            'fixes_applied' => $fixes,
            'scope_fix' => $scopeFix,
            'context_after_fix' => $ctx->toArray(),
            're_audit_summary' => $reAuditSummary,
            'total_fixes' => count($fixes),
            'auto_corrected_count' => $this->countAutoCorrected($fixes),
        ];
    }

    private function processAnomaly(array $anomaly, ?array $scopeFix): array
    {
        $handlers = [
            'SCOPE_MISMATCH' => fn() => $this->handleScopeMismatch(),
            'CROSS_TENANT_LEAK' => fn() => $this->handleCrossTenantLeak($anomaly),
            'VISIBLE_MISMATCH_ACTUAL_VISIBLE' => fn() => $this->handleVisibilityMismatchVisible($anomaly, $scopeFix),
            'VISIBLE_MISMATCH_ACTUAL_HIDDEN' => fn() => $this->handleVisibilityMismatchHidden($anomaly),
            'DEPT_SCOPE_OVERFLOW' => fn() => $this->handleDeptScopeOverflow($anomaly),
            'MODIFY_WITHOUT_VIEW' => fn() => $this->handleModifyWithoutView($anomaly),
        ];

        $type = $anomaly['type'];
        $handler = $handlers[$type] ?? fn() => $this->handleUnknownAnomaly($anomaly);

        return $handler();
    }

    private function handleScopeMismatch(): array
    {
        return [
            'type' => 'SCOPE_MISMATCH',
            'action' => 'scope_write_back',
            'result' => $this->applyAuditWriteBack(),
        ];
    }

    private function handleCrossTenantLeak(array $anomaly): array
    {
        return [
            'type' => 'CROSS_TENANT_LEAK',
            'action' => 'flag_for_manual_review',
            'result' => [
                'corrected' => false,
                'reason' => 'requires_manual_intervention',
                'message' => "跨租户泄露需人工审查，资源#{$anomaly['resource_id']}可能需要隔离或权限回收",
                'resource_id' => $anomaly['resource_id'],
            ],
        ];
    }

    private function handleVisibilityMismatchVisible(array $anomaly, ?array $scopeFix): array
    {
        return [
            'type' => 'VISIBLE_MISMATCH_ACTUAL_VISIBLE',
            'action' => 'scope_corrected_by_write_back',
            'result' => [
                'corrected' => $scopeFix ? $scopeFix['corrected'] : false,
                'message' => $scopeFix
                    ? $scopeFix['message']
                    : '需先修正可见范围偏差后重新核对',
                'resource_id' => $anomaly['resource_id'],
            ],
        ];
    }

    private function handleVisibilityMismatchHidden(array $anomaly): array
    {
        return [
            'type' => 'VISIBLE_MISMATCH_ACTUAL_HIDDEN',
            'action' => 'visibility_gap_noted',
            'result' => [
                'corrected' => false,
                'reason' => 'scope_too_narrow',
                'message' => "资源#{$anomaly['resource_id']}按角色默认范围应可见但实际不可见，可见范围可能被过度缩窄",
                'resource_id' => $anomaly['resource_id'],
            ],
        ];
    }

    private function handleDeptScopeOverflow(array $anomaly): array
    {
        return [
            'type' => 'DEPT_SCOPE_OVERFLOW',
            'action' => 'dept_scope_noted',
            'result' => [
                'corrected' => false,
                'reason' => 'dept_hierarchy_issue',
                'message' => "资源#{$anomaly['resource_id']}部门范围越界，需检查部门树配置",
                'resource_id' => $anomaly['resource_id'],
            ],
        ];
    }

    private function handleModifyWithoutView(array $anomaly): array
    {
        return [
            'type' => 'MODIFY_WITHOUT_VIEW',
            'action' => 'permission_config_noted',
            'result' => [
                'corrected' => false,
                'reason' => 'permission_asymmetry',
                'message' => "资源#{$anomaly['resource_id']}可修改但不可查看，权限配置异常",
                'resource_id' => $anomaly['resource_id'],
            ],
        ];
    }

    private function handleUnknownAnomaly(array $anomaly): array
    {
        return [
            'type' => $anomaly['type'] ?? 'UNKNOWN',
            'action' => 'no_action',
            'result' => [
                'corrected' => false,
                'reason' => 'unknown_anomaly_type',
                'message' => "未知异常类型，未执行修复",
                'resource_id' => $anomaly['resource_id'] ?? null,
            ],
        ];
    }

    private function runReAuditIfNeeded(?array $scopeFix, array $auditResult): ?array
    {
        if (!$scopeFix || !$scopeFix['corrected']) {
            return null;
        }

        $reAuditResources = $this->prepareReAuditResources($auditResult['audited_resources'] ?? []);
        if (empty($reAuditResources)) {
            return null;
        }

        $resourceType = $auditResult['context']['role'] ?? 'resource';
        $reAuditResult = $this->exportCrossRoleAudit($reAuditResources, $resourceType);
        return $reAuditResult['summary'];
    }

    private function prepareReAuditResources(array $auditedResources): array
    {
        return array_map(fn($ar) => [
            'id' => $ar['id'],
            'title' => $ar['title'],
            'owner_id' => $ar['owner_id'],
            'tenant_id' => $ar['tenant_id'],
            'dept_id' => $ar['dept_id'],
        ], $auditedResources);
    }

    private function countAutoCorrected(array $fixes): int
    {
        return count(array_filter(
            $fixes,
            fn($f) => ($f['result']['corrected'] ?? false) === true
        ));
    }
}
