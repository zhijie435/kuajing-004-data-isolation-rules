<?php

namespace App\Enums;

class DataScopeEnum
{
    const ALL = 1;
    const TENANT = 2;
    const DEPARTMENT = 3;
    const DEPARTMENT_AND_SUB = 4;
    const SELF = 5;
    const CUSTOM = 6;

    public static function labels(): array
    {
        return [
            self::ALL => '全部数据',
            self::TENANT => '本租户数据',
            self::DEPARTMENT => '本部门数据',
            self::DEPARTMENT_AND_SUB => '本部门及以下数据',
            self::SELF => '仅本人数据',
            self::CUSTOM => '自定义数据',
        ];
    }

    public static function label(int $value): string
    {
        return self::labels()[$value] ?? '未知';
    }
}
