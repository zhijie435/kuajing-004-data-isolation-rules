<?php

namespace App\Module\Core\Controller;

use App\Core\Enum\DataScopeLevel;
use App\Core\Service\DataVisibilityService;
use App\Core\Exception\ValidationException;

class DataScopeController
{
    private DataVisibilityService $visibility;

    public function __construct()
    {
        $this->visibility = new DataVisibilityService();
    }

    public function getScopeInfo(): array
    {
        return $this->visibility->getScopeSummary();
    }

    public function getAvailableScopes(): array
    {
        $scopes = $this->visibility->getAvailableScopes();
        return [
            'current' => \App\Core\Context\TenantContext::getInstance()->getDataScope()->value,
            'current_label' => \App\Core\Context\TenantContext::getInstance()->getDataScope()->label(),
            'available' => array_map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], $scopes),
        ];
    }

    public function switchScope(array $request): array
    {
        $body = $request['body'] ?? [];
        $scopeValue = (int)($body['scope'] ?? 0);

        $target = DataScopeLevel::tryFrom($scopeValue);
        if (!$target) {
            throw new ValidationException('无效的数据可见范围值');
        }

        return $this->visibility->switchScope($target);
    }

    public function checkResourceAccess(array $request): array
    {
        $body = $request['body'] ?? [];
        $resource = $body['resource'] ?? [];
        $action = $body['action'] ?? 'view';

        if (empty($resource)) {
            throw new ValidationException('缺少资源数据');
        }

        if ($action === 'modify') {
            $allowed = $this->visibility->canModifyResource($resource);
        } else {
            $allowed = $this->visibility->canViewResource($resource);
        }

        return [
            'action' => $action,
            'allowed' => $allowed,
            'resource' => $resource,
            'scope_context' => $this->visibility->getScopeSummary(),
        ];
    }

    public function crossRoleFilter(array $request): array
    {
        $body = $request['body'] ?? [];
        $targetRoles = $body['target_roles'] ?? [];
        return [
            'visible_roles' => $this->visibility->buildCrossRoleFilter($targetRoles),
            'target_roles_input' => $targetRoles,
        ];
    }

    public function crossRoleAudit(array $request): array
    {
        $body = $request['body'] ?? [];
        $resources = $body['resources'] ?? [];
        $resourceType = $body['resource_type'] ?? 'resource';

        if (empty($resources)) {
            throw new ValidationException('缺少待核对的资源数据');
        }

        return $this->visibility->exportCrossRoleAudit($resources, $resourceType);
    }

    public function auditFix(array $request): array
    {
        $body = $request['body'] ?? [];
        $auditResult = $body['audit_result'] ?? null;

        if ($auditResult === null) {
            throw new ValidationException('缺少审核结果数据');
        }

        return $this->visibility->applyCrossRoleAuditFix($auditResult);
    }
}
