<?php

namespace App\Core\Orm;

use App\Core\Context\TenantContext;
use App\Core\Enum\DataScopeLevel;

class TenantScope
{
    private TenantContext $context;
    private string $tenantColumn = 'tenant_id';
    private string $deptColumn = 'dept_id';
    private string $teamColumn = 'team_id';
    private string $ownerColumn = 'owner_id';
    private string $createdByColumn = 'created_by';

    private bool $ignoreTenant = false;
    private bool $ignoreDataScope = false;

    public function __construct()
    {
        $this->context = TenantContext::getInstance();
    }

    public function ignoreTenant(bool $ignore = true): self
    {
        $this->ignoreTenant = $ignore;
        return $this;
    }

    public function ignoreDataScope(bool $ignore = true): self
    {
        $this->ignoreDataScope = $ignore;
        return $this;
    }

    public function apply(string $tableAlias = '', array $options = []): array
    {
        $conditions = [];
        $params = [];
        $alias = $tableAlias ? "{$tableAlias}." : '';

        $opts = array_merge([
            'tenant_column' => $this->tenantColumn,
            'dept_column' => $this->deptColumn,
            'team_column' => $this->teamColumn,
            'owner_column' => $this->ownerColumn,
            'created_by_column' => $this->createdByColumn,
        ], $options);

        if (!$this->ignoreTenant) {
            [$tenantWhere, $tenantParams] = $this->buildTenantCondition($alias, $opts['tenant_column']);
            if ($tenantWhere) {
                $conditions[] = $tenantWhere;
                $params = array_merge($params, $tenantParams);
            }
        }

        if (!$this->ignoreDataScope) {
            [$scopeWhere, $scopeParams] = $this->buildDataScopeCondition($alias, $opts);
            if ($scopeWhere) {
                $conditions[] = $scopeWhere;
                $params = array_merge($params, $scopeParams);
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function applyToQuery(string $sql, string $tableAlias = '', array $options = []): array
    {
        [$where, $params] = $this->apply($tableAlias, $options);

        if (!$where) {
            return [$sql, $params];
        }

        if (stripos($sql, 'WHERE') !== false) {
            $sql = preg_replace('/\bWHERE\b/i', "WHERE {$where} AND ", $sql, 1);
        } else {
            $sql .= " WHERE {$where}";
        }

        return [$sql, $params];
    }

    private function buildTenantCondition(string $alias, string $column): array
    {
        $tenantId = $this->context->getTenantId();

        if ($this->context->isSuperAdmin() && $tenantId === null) {
            return [null, []];
        }

        if ($tenantId === null) {
            return ["{$alias}{$column} IS NULL", []];
        }

        return ["{$alias}{$column} = ?", [$tenantId]];
    }

    private function buildDataScopeCondition(string $alias, array $opts): array
    {
        $scope = $this->context->getDataScope();

        switch ($scope) {
            case DataScopeLevel::ALL:
                return [null, []];

            case DataScopeLevel::TENANT:
                return [null, []];

            case DataScopeLevel::DEPARTMENT:
                $deptIds = $this->context->getDeptChildIds();
                if (empty($deptIds)) {
                    $deptId = $this->context->getDeptId();
                    if ($deptId === null) {
                        return ['1 = 0', []];
                    }
                    return ["{$alias}{$opts['dept_column']} = ?", [$deptId]];
                }
                $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
                return ["{$alias}{$opts['dept_column']} IN ({$placeholders})", $deptIds];

            case DataScopeLevel::TEAM:
                $memberIds = $this->context->getTeamMemberIds();
                if (empty($memberIds)) {
                    $userId = $this->context->getUserId();
                    return ["{$alias}{$opts['owner_column']} = ?", [$userId]];
                }
                $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
                return ["{$alias}{$opts['owner_column']} IN ({$placeholders})", $memberIds];

            case DataScopeLevel::SELF:
            default:
                $userId = $this->context->getUserId();
                return ["({$alias}{$opts['owner_column']} = ? OR {$alias}{$opts['created_by_column']} = ?)", [$userId, $userId]];
        }
    }

    public function describe(): string
    {
        $ctx = TenantContext::getInstance();
        return sprintf(
            '[Tenant=%s | Scope=%s | User=%s(%s)]',
            $ctx->getTenantId() ?? 'ALL',
            $ctx->getDataScope()->label(),
            $ctx->getUsername(),
            $ctx->getRole()?->label() ?? '?'
        );
    }
}
