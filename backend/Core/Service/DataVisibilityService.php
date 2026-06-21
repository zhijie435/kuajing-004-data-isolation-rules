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
        $allowed = false;
        foreach ($available as $s) {
            if ($s->value === $target->value) {
                $allowed = true;
                break;
            }
        }

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

        $roleScopeExpectation = [
            RoleType::SUPER_ADMIN->value => [
                'scope' => DataScopeLevel::ALL,
                'scope_label' => DataScopeLevel::ALL->label(),
                'expected_visibility' => '可查看所有租户所有数据',
            ],
            RoleType::TENANT_ADMIN->value => [
                'scope' => DataScopeLevel::TENANT,
                'scope_label' => DataScopeLevel::TENANT->label(),
                'expected_visibility' => '可查看本租户全部数据',
            ],
            RoleType::DEPT_HEAD->value => [
                'scope' => DataScopeLevel::DEPARTMENT,
                'scope_label' => DataScopeLevel::DEPARTMENT->label(),
                'expected_visibility' => '可查看本部门及下级部门数据',
            ],
            RoleType::TEAM_LEADER->value => [
                'scope' => DataScopeLevel::TEAM,
                'scope_label' => DataScopeLevel::TEAM->label(),
                'expected_visibility' => '可查看本团队成员数据',
            ],
            RoleType::TEACHER->value => [
                'scope' => DataScopeLevel::SELF,
                'scope_label' => DataScopeLevel::SELF->label(),
                'expected_visibility' => '仅可查看本人创建/负责的数据',
            ],
            RoleType::STUDENT->value => [
                'scope' => DataScopeLevel::SELF,
                'scope_label' => DataScopeLevel::SELF->label(),
                'expected_visibility' => '仅可查看本人创建/负责的数据',
            ],
        ];

        $roleVisibilityMap = [];
        foreach (RoleType::cases() as $rt) {
            $savedContext = $this->cloneContextSnapshot();

            $roleVisibilityMap[$rt->value] = [
                'role' => $rt->value,
                'role_label' => $rt->label(),
                'default_scope' => $rt->defaultDataScope()->value,
                'default_scope_label' => $rt->defaultDataScope()->label(),
                'expected_visibility' => $roleScopeExpectation[$rt->value]['expected_visibility'],
            ];
        }

        $currentExpectation = $roleScopeExpectation[$currentRole?->value ?? RoleType::STUDENT->value] ?? null;
        $scopeMismatch = false;
        if ($currentExpectation && $currentScope->value !== $currentExpectation['scope']->value) {
            $scopeMismatch = true;
        }

        $auditedResources = [];
        $anomalies = [];
        $warningCount = 0;
        $errorCount = 0;

        foreach ($resources as $resource) {
            $actualVisible = $this->canViewResource($resource);
            $actualModifiable = $this->canModifyResource($resource);

            $expectedVisible = $this->computeExpectedVisibility($resource, $currentRole, $currentScope);

            $resourceAnomaly = null;
            if ($actualVisible !== $expectedVisible) {
                $resourceAnomaly = $actualVisible
                    ? 'VISIBLE_MISMATCH_ACTUAL_VISIBLE'
                    : 'VISIBLE_MISMATCH_ACTUAL_HIDDEN';
                $errorCount++;
                $anomalies[] = [
                    'type' => $resourceAnomaly,
                    'severity' => 'error',
                    'resource_id' => $resource['id'] ?? null,
                    'resource_type' => $resourceType,
                    'detail' => $actualVisible
                        ? "资源#{$resource['id']}实际可见但按规则不应可见（数据越权泄露风险）"
                        : "资源#{$resource['id']}按规则应可见但实际不可见（数据可见性缺失）",
                    'expected' => $expectedVisible,
                    'actual' => $actualVisible,
                    'resource_owner_id' => $resource['owner_id'] ?? $resource['created_by'] ?? null,
                    'resource_tenant_id' => $resource['tenant_id'] ?? null,
                ];
            }

            $crossTenantLeak = false;
            if ($actualVisible && $currentRole !== RoleType::SUPER_ADMIN && $currentScope !== DataScopeLevel::ALL) {
                $resourceTenantId = $resource['tenant_id'] ?? null;
                $currentTenantId = $ctx->getTenantId();
                if ($resourceTenantId !== null && $currentTenantId !== null && $resourceTenantId != $currentTenantId) {
                    $crossTenantLeak = true;
                    $errorCount++;
                    $anomalies[] = [
                        'type' => 'CROSS_TENANT_LEAK',
                        'severity' => 'error',
                        'resource_id' => $resource['id'] ?? null,
                        'resource_type' => $resourceType,
                        'detail' => "资源#{$resource['id']}属于租户{$resourceTenantId}，但当前用户租户为{$currentTenantId}，存在跨租户数据泄露",
                        'resource_tenant_id' => $resourceTenantId,
                        'current_tenant_id' => $currentTenantId,
                    ];
                }
            }

            $modifyWithoutView = false;
            if ($actualModifiable && !$actualVisible) {
                $modifyWithoutView = true;
                $warningCount++;
                $anomalies[] = [
                    'type' => 'MODIFY_WITHOUT_VIEW',
                    'severity' => 'warning',
                    'resource_id' => $resource['id'] ?? null,
                    'resource_type' => $resourceType,
                    'detail' => "资源#{$resource['id']}可修改但不可查看，权限配置可能异常",
                ];
            }

            $ownerMismatch = false;
            $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
            $resourceDeptId = $resource['dept_id'] ?? null;
            if ($actualVisible && $currentRole === RoleType::DEPT_HEAD && $resourceDeptId !== null) {
                $deptChildIds = $ctx->getDeptChildIds();
                if (!empty($deptChildIds) && !in_array($resourceDeptId, $deptChildIds, true)) {
                    $ownerMismatch = true;
                    $warningCount++;
                    $anomalies[] = [
                        'type' => 'DEPT_SCOPE_OVERFLOW',
                        'severity' => 'warning',
                        'resource_id' => $resource['id'] ?? null,
                        'resource_type' => $resourceType,
                        'detail' => "资源#{$resource['id']}所属部门{$resourceDeptId}不在当前用户管辖范围" . json_encode($deptChildIds) . "内，但数据可见",
                        'resource_dept_id' => $resourceDeptId,
                        'managed_dept_ids' => $deptChildIds,
                    ];
                }
            }

            $auditedResources[] = [
                'id' => $resource['id'] ?? null,
                'title' => $resource['title'] ?? ($resource['name'] ?? null),
                'owner_id' => $ownerId,
                'tenant_id' => $resource['tenant_id'] ?? null,
                'dept_id' => $resourceDeptId,
                'actual_visible' => $actualVisible,
                'expected_visible' => $expectedVisible,
                'actual_modifiable' => $actualModifiable,
                'anomaly' => $resourceAnomaly,
                'cross_tenant_leak' => $crossTenantLeak,
                'modify_without_view' => $modifyWithoutView,
                'dept_scope_overflow' => $ownerMismatch,
            ];
        }

        $totalResources = count($resources);
        $actualVisibleCount = count(array_filter($auditedResources, fn($r) => $r['actual_visible']));
        $expectedVisibleCount = count(array_filter($auditedResources, fn($r) => $r['expected_visible']));
        $anomalyCount = count($anomalies);

        $overallStatus = 'healthy';
        if ($errorCount > 0) {
            $overallStatus = 'error';
        } elseif ($warningCount > 0) {
            $overallStatus = 'warning';
        } elseif ($scopeMismatch) {
            $overallStatus = 'warning';
        }

        $summary = [
            'total_resources' => $totalResources,
            'actual_visible_count' => $actualVisibleCount,
            'expected_visible_count' => $expectedVisibleCount,
            'visible_count_match' => $actualVisibleCount === $expectedVisibleCount,
            'anomaly_count' => $anomalyCount,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'overall_status' => $overallStatus,
        ];

        $roleVisibilityExport = [];
        foreach ($roleVisibilityMap as $roleKey => $roleInfo) {
            $roleVisibilityExport[] = $roleInfo;
        }

        $contextSnapshot = [
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

        if ($scopeMismatch) {
            $anomalies[] = [
                'type' => 'SCOPE_MISMATCH',
                'severity' => 'warning',
                'resource_id' => null,
                'resource_type' => $resourceType,
                'detail' => "当前数据可见范围为「{$currentScope->label()}」，但角色默认范围为「{$currentExpectation['scope']->label()}」，可能存在越权或范围缩窄",
                'current_scope' => $currentScope->value,
                'default_scope' => $currentExpectation['scope']->value,
            ];
            $warningCount++;
            $summary['warning_count'] = $warningCount;
            $summary['anomaly_count'] = count($anomalies);
            if ($overallStatus === 'healthy') {
                $summary['overall_status'] = 'warning';
            }
        }

        return [
            'summary' => $summary,
            'context' => $contextSnapshot,
            'role_visibility_export' => $roleVisibilityExport,
            'audited_resources' => $auditedResources,
            'anomalies' => $anomalies,
        ];
    }

    private function computeExpectedVisibility(array $resource, ?RoleType $role, DataScopeLevel $scope): bool
    {
        if ($role === null) return false;

        $defaultScope = $role->defaultDataScope();

        return match (true) {
            $defaultScope === DataScopeLevel::ALL => true,

            $defaultScope === DataScopeLevel::TENANT => ($resource['tenant_id'] ?? null) == $this->context->getTenantId(),

            $defaultScope === DataScopeLevel::DEPARTMENT => (function () use ($resource) {
                $deptIds = $this->context->getDeptChildIds();
                if (empty($deptIds)) {
                    $deptId = $this->context->getDeptId();
                    return $deptId !== null && ($resource['dept_id'] ?? null) == $deptId;
                }
                return in_array($resource['dept_id'] ?? null, $deptIds, true);
            })(),

            $defaultScope === DataScopeLevel::TEAM => (function () use ($resource) {
                $memberIds = $this->context->getTeamMemberIds();
                $ownerId = $resource['owner_id'] ?? $resource['created_by'] ?? null;
                if (empty($memberIds)) {
                    return $ownerId == $this->context->getUserId();
                }
                return in_array($ownerId, $memberIds, true);
            })(),

            default => ($resource['owner_id'] ?? null) == $this->context->getUserId()
                || ($resource['created_by'] ?? null) == $this->context->getUserId()
                || ($resource['user_id'] ?? null) == $this->context->getUserId(),
        };
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
            switch ($anomaly['type']) {
                case 'SCOPE_MISMATCH':
                    $scopeFix = $this->applyAuditWriteBack();
                    $fixes[] = [
                        'type' => 'SCOPE_MISMATCH',
                        'action' => 'scope_write_back',
                        'result' => $scopeFix,
                    ];
                    break;

                case 'CROSS_TENANT_LEAK':
                    $fixes[] = [
                        'type' => 'CROSS_TENANT_LEAK',
                        'action' => 'flag_for_manual_review',
                        'result' => [
                            'corrected' => false,
                            'reason' => 'requires_manual_intervention',
                            'message' => "跨租户泄露需人工审查，资源#{$anomaly['resource_id']}可能需要隔离或权限回收",
                            'resource_id' => $anomaly['resource_id'],
                        ],
                    ];
                    break;

                case 'VISIBLE_MISMATCH_ACTUAL_VISIBLE':
                    $fixes[] = [
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
                    break;

                case 'VISIBLE_MISMATCH_ACTUAL_HIDDEN':
                    $fixes[] = [
                        'type' => 'VISIBLE_MISMATCH_ACTUAL_HIDDEN',
                        'action' => 'visibility_gap_noted',
                        'result' => [
                            'corrected' => false,
                            'reason' => 'scope_too_narrow',
                            'message' => "资源#{$anomaly['resource_id']}按角色默认范围应可见但实际不可见，可见范围可能被过度缩窄",
                            'resource_id' => $anomaly['resource_id'],
                        ],
                    ];
                    break;

                case 'DEPT_SCOPE_OVERFLOW':
                    $fixes[] = [
                        'type' => 'DEPT_SCOPE_OVERFLOW',
                        'action' => 'dept_scope_noted',
                        'result' => [
                            'corrected' => false,
                            'reason' => 'dept_hierarchy_issue',
                            'message' => "资源#{$anomaly['resource_id']}部门范围越界，需检查部门树配置",
                            'resource_id' => $anomaly['resource_id'],
                        ],
                    ];
                    break;

                case 'MODIFY_WITHOUT_VIEW':
                    $fixes[] = [
                        'type' => 'MODIFY_WITHOUT_VIEW',
                        'action' => 'permission_config_noted',
                        'result' => [
                            'corrected' => false,
                            'reason' => 'permission_asymmetry',
                            'message' => "资源#{$anomaly['resource_id']}可修改但不可查看，权限配置异常",
                            'resource_id' => $anomaly['resource_id'],
                        ],
                    ];
                    break;
            }
        }

        $reAuditSummary = null;
        if ($scopeFix && $scopeFix['corrected']) {
            $reAuditResources = [];
            foreach ($auditResult['audited_resources'] ?? [] as $ar) {
                $reAuditResources[] = [
                    'id' => $ar['id'],
                    'title' => $ar['title'],
                    'owner_id' => $ar['owner_id'],
                    'tenant_id' => $ar['tenant_id'],
                    'dept_id' => $ar['dept_id'],
                ];
            }
            if (!empty($reAuditResources)) {
                $reAuditResult = $this->exportCrossRoleAudit($reAuditResources, $auditResult['context']['role'] ?? 'resource');
                $reAuditSummary = $reAuditResult['summary'];
            }
        }

        return [
            'fixes_applied' => $fixes,
            'scope_fix' => $scopeFix,
            'context_after_fix' => $ctx->toArray(),
            're_audit_summary' => $reAuditSummary,
            'total_fixes' => count($fixes),
            'auto_corrected_count' => count(array_filter($fixes, fn($f) => ($f['result']['corrected'] ?? false) === true)),
        ];
    }
}
