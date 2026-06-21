<?php

namespace App\Module\Course\Model;

use App\Core\Database\QueryBuilder;

class CourseModel
{
    public const TABLE = 'courses';

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(self::TABLE, 'c');
    }

    public static function findAllByFilter(array $filter = []): array
    {
        $q = self::query()->select([
            'c.id',
            'c.tenant_id',
            'c.dept_id',
            'c.team_id',
            'c.owner_id',
            'c.created_by',
            'c.title',
            'c.category',
            'c.status',
            'c.student_count',
            'c.created_at',
        ]);

        if (!empty($filter['status'])) {
            $q->where('c.status', $filter['status']);
        }
        if (!empty($filter['category'])) {
            $q->where('c.category', $filter['category']);
        }
        if (!empty($filter['keyword'])) {
            $q->where('c.title', 'LIKE', "%{$filter['keyword']}%");
        }

        $q->orderBy('c.created_at', 'DESC')->limit(50);

        return self::simulateResultSet($q);
    }

    public static function findById(int $id): ?array
    {
        $all = self::mockData();
        return $all[$id] ?? null;
    }

    public static function countByScope(): array
    {
        $q = self::query();
        return [
            'sql' => $q->toSql(),
            'params' => $q->getParams(),
            'note' => 'COUNT查询同样会自动应用租户+数据范围过滤',
        ];
    }

    public static function debugQuery(array $filter = []): array
    {
        return self::query()->where('status', 'published')->debug();
    }

    private static function simulateResultSet(QueryBuilder $q): array
    {
        $all = self::mockData();
        $scopeInfo = $q->debug();

        $sql = $scopeInfo['sql'];
        $visibleIds = self::resolveVisibleIds($sql, $scopeInfo['params']);

        $rows = [];
        foreach ($all as $id => $course) {
            if (in_array($id, $visibleIds, true)) {
                if (!empty($filter['status']) && $course['status'] !== $filter['status']) continue;
                if (!empty($filter['category']) && $course['category'] !== $filter['category']) continue;
                if (!empty($filter['keyword']) && !str_contains($course['title'], $filter['keyword'])) continue;
                $rows[] = $course;
            }
        }

        return [
            'total' => count($rows),
            'list' => $rows,
            '_scope_debug' => $scopeInfo,
        ];
    }

    private static function resolveVisibleIds(string $sql, array $params): array
    {
        $all = self::mockData();
        $result = [];

        $tenantMatch = [];
        if (preg_match('/tenant_id\s*=\s*\?/', $sql, $tenantMatch)) {
            $tid = $params[0] ?? null;
            foreach ($all as $id => $c) {
                if ($c['tenant_id'] == $tid) $result[] = $id;
            }
        } elseif (preg_match('/tenant_id\s+IS\s+NULL/', $sql)) {
            foreach ($all as $id => $c) {
                if ($c['tenant_id'] === null) $result[] = $id;
            }
        } else {
            $result = array_keys($all);
        }

        if (preg_match('/dept_id\s+IN\s*\(([^)]+)\)/', $sql, $deptMatch)) {
            $count = substr_count($deptMatch[1], '?');
            $startIdx = count($params) > $count ? count($params) - $count : 0;
            $deptIds = array_slice($params, $startIdx, $count);
            $result = array_values(array_filter($result, function($id) use ($all, $deptIds) {
                return in_array($all[$id]['dept_id'], $deptIds, true);
            }));
        } elseif (preg_match('/dept_id\s*=\s*\?/', $sql, $deptMatch)) {
            $deptParam = end($params);
            $result = array_values(array_filter($result, function($id) use ($all, $deptParam) {
                return $all[$id]['dept_id'] == $deptParam;
            }));
        }

        if (preg_match('/owner_id\s+IN\s*\(([^)]+)\)/', $sql, $ownerMatch)) {
            $count = substr_count($ownerMatch[1], '?');
            $ownerIds = array_slice($params, -$count);
            $result = array_values(array_filter($result, function($id) use ($all, $ownerIds) {
                return in_array($all[$id]['owner_id'], $ownerIds, true);
            }));
        }

        if (preg_match('/\(owner_id\s*=\s*\?\s+OR\s+created_by\s*=\s*\?\)/', $sql)) {
            $uid = end($params);
            $result = array_values(array_filter($result, function($id) use ($all, $uid) {
                return $all[$id]['owner_id'] == $uid || $all[$id]['created_by'] == $uid;
            }));
        }

        if (preg_match('/1\s*=\s*0/', $sql)) {
            return [];
        }

        return $result;
    }

    public static function mockData(): array
    {
        return [
            1001 => ['id' => 1001, 'tenant_id' => 1, 'dept_id' => 2, 'team_id' => 101, 'owner_id' => 202, 'created_by' => 202, 'title' => 'PHP高级编程实战', 'category' => '编程', 'status' => 'published', 'student_count' => 320, 'created_at' => '2026-01-10 09:00:00'],
            1002 => ['id' => 1002, 'tenant_id' => 1, 'dept_id' => 4, 'team_id' => 101, 'owner_id' => 203, 'created_by' => 203, 'title' => '小学语文作文提升', 'category' => '语文', 'status' => 'published', 'student_count' => 156, 'created_at' => '2026-02-01 10:30:00'],
            1003 => ['id' => 1003, 'tenant_id' => 1, 'dept_id' => 4, 'team_id' => 101, 'owner_id' => 202, 'created_by' => 202, 'title' => '中学语文文言文精讲', 'category' => '语文', 'status' => 'draft', 'student_count' => 0, 'created_at' => '2026-02-15 14:20:00'],
            1004 => ['id' => 1004, 'tenant_id' => 1, 'dept_id' => 5, 'team_id' => 102, 'owner_id' => 301, 'created_by' => 301, 'title' => '高等数学微积分入门', 'category' => '数学', 'status' => 'published', 'student_count' => 280, 'created_at' => '2026-03-01 08:15:00'],
            1005 => ['id' => 1005, 'tenant_id' => 1, 'dept_id' => 5, 'team_id' => 102, 'owner_id' => 302, 'created_by' => 302, 'title' => '线性代数考点精讲', 'category' => '数学', 'status' => 'published', 'student_count' => 198, 'created_at' => '2026-03-10 11:00:00'],
            1006 => ['id' => 1006, 'tenant_id' => 2, 'dept_id' => 2, 'team_id' => 103, 'owner_id' => 401, 'created_by' => 401, 'title' => 'Vue3企业级项目开发', 'category' => '编程', 'status' => 'published', 'student_count' => 410, 'created_at' => '2026-01-20 09:30:00'],
            1007 => ['id' => 1007, 'tenant_id' => 2, 'dept_id' => 3, 'team_id' => null, 'owner_id' => 402, 'created_by' => 402, 'title' => '教育机构品牌营销策略', 'category' => '市场', 'status' => 'published', 'student_count' => 85, 'created_at' => '2026-04-01 15:45:00'],
            1008 => ['id' => 1008, 'tenant_id' => 3, 'dept_id' => 2, 'team_id' => null, 'owner_id' => 501, 'created_by' => 501, 'title' => '英语口语对话速成', 'category' => '英语', 'status' => 'published', 'student_count' => 520, 'created_at' => '2026-02-28 10:00:00'],
            1009 => ['id' => 1009, 'tenant_id' => null, 'dept_id' => null, 'team_id' => null, 'owner_id' => 999, 'created_by' => 999, 'title' => '【平台公共】系统操作入门指南', 'category' => '通用', 'status' => 'published', 'student_count' => 9999, 'created_at' => '2025-12-01 00:00:00'],
            1010 => ['id' => 1010, 'tenant_id' => 1, 'dept_id' => 7, 'team_id' => null, 'owner_id' => 204, 'created_by' => 204, 'title' => '高考语文阅读理解技巧', 'category' => '语文', 'status' => 'published', 'student_count' => 210, 'created_at' => '2026-03-20 13:00:00'],
        ];
    }
}
