<?php

namespace App\Core\Context;

use App\Core\Enum\DataScopeLevel;
use App\Core\Enum\RoleType;

class TenantContext
{
    private static ?self $instance = null;

    private ?int $tenantId = null;
    private ?int $userId = null;
    private ?string $username = null;
    private ?RoleType $role = null;
    private ?DataScopeLevel $dataScope = null;
    private ?int $deptId = null;
    private ?int $teamId = null;
    private array $deptChildIds = [];
    private array $teamMemberIds = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bootstrap(array $payload): void
    {
        $this->tenantId = $payload['tenant_id'] ?? null;
        $this->userId = $payload['user_id'] ?? null;
        $this->username = $payload['username'] ?? null;
        $this->role = isset($payload['role']) ? RoleType::from($payload['role']) : null;
        $this->deptId = $payload['dept_id'] ?? null;
        $this->teamId = $payload['team_id'] ?? null;
        $this->deptChildIds = $payload['dept_child_ids'] ?? [];
        $this->teamMemberIds = $payload['team_member_ids'] ?? [];

        $this->dataScope = isset($payload['data_scope'])
            ? DataScopeLevel::from($payload['data_scope'])
            : ($this->role?->defaultDataScope() ?? DataScopeLevel::SELF);
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getRole(): ?RoleType
    {
        return $this->role;
    }

    public function getDataScope(): DataScopeLevel
    {
        return $this->dataScope ?? DataScopeLevel::SELF;
    }

    public function setDataScope(DataScopeLevel $scope): void
    {
        if ($this->role && $scope->gte($this->role->defaultDataScope())) {
            throw new \RuntimeException('越权设置数据可见范围');
        }
        $this->dataScope = $scope;
    }

    public function getDeptId(): ?int
    {
        return $this->deptId;
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    public function getDeptChildIds(): array
    {
        return $this->deptChildIds;
    }

    public function getTeamMemberIds(): array
    {
        return $this->teamMemberIds;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === RoleType::SUPER_ADMIN;
    }

    public function reset(): void
    {
        $this->tenantId = null;
        $this->userId = null;
        $this->username = null;
        $this->role = null;
        $this->dataScope = null;
        $this->deptId = null;
        $this->teamId = null;
        $this->deptChildIds = [];
        $this->teamMemberIds = [];
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'username' => $this->username,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'data_scope' => $this->dataScope?->value,
            'data_scope_label' => $this->dataScope?->label(),
            'dept_id' => $this->deptId,
            'team_id' => $this->teamId,
        ];
    }
}
