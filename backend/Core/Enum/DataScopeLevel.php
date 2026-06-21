<?php

namespace App\Core\Enum;

enum DataScopeLevel: int
{
    case ALL = 1;
    case TENANT = 2;
    case DEPARTMENT = 3;
    case TEAM = 4;
    case SELF = 5;

    public function label(): string
    {
        return match ($this) {
            self::ALL => '全部数据',
            self::TENANT => '本租户数据',
            self::DEPARTMENT => '本部门及下级',
            self::TEAM => '本团队数据',
            self::SELF => '仅本人数据',
        };
    }

    public function gte(DataScopeLevel $other): bool
    {
        return $this->value <= $other->value;
    }
}
