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

        $sql = $this->injectWhereClause($sql, $where);

        return [$sql, $params];
    }

    private function injectWhereClause(string $sql, string $condition): string
    {
        $trimmedSql = rtrim($sql, "; \t\n\r");

        if (preg_match('/\s+ORDER\s+BY\s+/i', $trimmedSql, $matches, PREG_OFFSET_CAPTURE)) {
            $orderPos = $matches[0][1];
            return substr($trimmedSql, 0, $orderPos) . " WHERE {$condition}" . substr($trimmedSql, $orderPos);
        }

        if (preg_match('/\s+GROUP\s+BY\s+/i', $trimmedSql, $matches, PREG_OFFSET_CAPTURE)) {
            $groupPos = $matches[0][1];
            return substr($trimmedSql, 0, $groupPos) . " WHERE {$condition}" . substr($trimmedSql, $groupPos);
        }

        if (preg_match('/\s+LIMIT\s+/i', $trimmedSql, $matches, PREG_OFFSET_CAPTURE)) {
            $limitPos = $matches[0][1];
            return substr($trimmedSql, 0, $limitPos) . " WHERE {$condition}" . substr($trimmedSql, $limitPos);
        }

        if (preg_match('/\s+WHERE\s+/i', $trimmedSql, $matches, PREG_OFFSET_CAPTURE)) {
            $wherePos = $matches[0][1];
            return substr($trimmedSql, 0, $wherePos) . " WHERE {$condition} AND " . substr($trimmedSql, $wherePos + strlen($matches[0][0]));
        }

        return $trimmedSql . " WHERE {$condition}";
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

        return match ($scope) {
            DataScopeLevel::ALL => [null, []],
            DataScopeLevel::TENANT => [null, []],
            DataScopeLevel::DEPARTMENT => $this->buildDeptCondition($alias, $opts),
            DataScopeLevel::TEAM => $this->buildTeamCondition($alias, $opts),
            DataScopeLevel::SELF => $this->buildSelfCondition($alias, $opts),
        };
    }

    private function buildDeptCondition(string $alias, array $opts): array
    {
        $deptIds = $this->context->getDeptChildIds();

        if (empty($deptIds)) {
            $deptId = $this->context->getDeptId();
            if ($deptId === null) {
                return ['1 = 0', []];
            }
            return ["{$alias}{$opts['dept_column']} = ?", [$deptId]];
        }

        $placeholders = $this->buildPlaceholders($deptIds);
        return ["{$alias}{$opts['dept_column']} IN ({$placeholders})", $deptIds];
    }

    private function buildTeamCondition(string $alias, array $opts): array
    {
        $memberIds = $this->context->getTeamMemberIds();

        if (empty($memberIds)) {
            return $this->buildSelfCondition($alias, $opts);
        }

        $placeholders = $this->buildPlaceholders($memberIds);
        return ["{$alias}{$opts['owner_column']} IN ({$placeholders})", $memberIds];
    }

    private function buildSelfCondition(string $alias, array $opts): array
    {
        $userId = $this->context->getUserId();
        return [
            "({$alias}{$opts['owner_column']} = ? OR {$alias}{$opts['created_by_column']} = ?)",
            [$userId, $userId]
        ];
    }

    private function buildPlaceholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
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
