<?php

namespace App\Core\Enum;

enum RoleType: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_ADMIN = 'tenant_admin';
    case DEPT_HEAD = 'dept_head';
    case TEAM_LEADER = 'team_leader';
    case TEACHER = 'teacher';
    case STUDENT = 'student';

    public function defaultDataScope(): DataScopeLevel
    {
        return match ($this) {
            self::SUPER_ADMIN => DataScopeLevel::ALL,
            self::TENANT_ADMIN => DataScopeLevel::TENANT,
            self::DEPT_HEAD => DataScopeLevel::DEPARTMENT,
            self::TEAM_LEADER => DataScopeLevel::TEAM,
            self::TEACHER, self::STUDENT => DataScopeLevel::SELF,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => '超级管理员',
            self::TENANT_ADMIN => '租户管理员',
            self::DEPT_HEAD => '部门主管',
            self::TEAM_LEADER => '团队负责人',
            self::TEACHER => '讲师',
            self::STUDENT => '学员',
        };
    }
}
