<?php

namespace App\Module\Course\Model;

use App\Core\Database\QueryBuilder;
use App\Core\Storage\InMemoryDataStore;
use App\Core\Context\TenantContext;
use App\Core\Service\DataVisibilityService;

class CourseModel
{
    public const TABLE = 'courses';

    private static bool $initialized = false;

    private static function ensureInitialized(): void
    {
        if (self::$initialized) return;

        $store = InMemoryDataStore::getInstance();
        $store->initTable(self::TABLE, self::mockData());
        self::$initialized = true;
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(self::TABLE, 'c');
    }

    public static function findAllByFilter(array $filter = []): array
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();

        $q = self::query()->select([
            'c.id', 'c.tenant_id', 'c.dept_id', 'c.team_id',
            'c.owner_id', 'c.created_by', 'c.title', 'c.category',
            'c.status', 'c.student_count', 'c.created_at',
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

        $q->orderBy('c.created_at', 'DESC')->limit(100);

        return self::simulateResultSet($q, $filter);
    }

    public static function findById(int $id): ?array
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();
        $row = $store->find(self::TABLE, $id);
        if (!$row) return null;

        $visibility = new DataVisibilityService();
        if (!$visibility->canViewResource($row)) {
            return null;
        }

        return $row;
    }

    public static function findByIdRaw(int $id): ?array
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();
        return $store->find(self::TABLE, $id) ?? null;
    }

    public static function create(array $data): array
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();
        $ctx = TenantContext::getInstance();

        $id = $store->nextId();
        $course = [
            'id' => $id,
            'tenant_id' => $data['tenant_id'] ?? $ctx->getTenantId(),
            'dept_id' => $data['dept_id'] ?? $ctx->getDeptId(),
            'team_id' => $data['team_id'] ?? $ctx->getTeamId(),
            'owner_id' => $data['owner_id'] ?? $ctx->getUserId(),
            'created_by' => $ctx->getUserId(),
            'title' => $data['title'],
            'category' => $data['category'] ?? '未分类',
            'status' => $data['status'] ?? 'draft',
            'student_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $store->insert(self::TABLE, $course);
    }

    public static function update(int $id, array $data): ?array
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();

        $updatable = ['title', 'category', 'status', 'student_count'];
        $updates = [];
        foreach ($updatable as $k) {
            if (isset($data[$k])) {
                $updates[$k] = $data[$k];
            }
        }

        if (empty($updates)) {
            return $store->find(self::TABLE, $id);
        }

        return $store->update(self::TABLE, $id, $updates);
    }

    public static function delete(int $id): bool
    {
        self::ensureInitialized();
        $store = InMemoryDataStore::getInstance();
        return $store->delete(self::TABLE, $id);
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

    public static function resetData(): void
    {
        self::ensureInitialized();
        InMemoryDataStore::getInstance()->resetTable(self::TABLE);
    }

    private static function simulateResultSet(QueryBuilder $q, array $filter = []): array
    {
        $store = InMemoryDataStore::getInstance();
        $all = $store->all(self::TABLE);
        $scopeInfo = $q->debug();

        $sql = $scopeInfo['sql'];
        $params = $scopeInfo['params'];
        $visibleIds = self::resolveVisibleIds($sql, $params, $all);

        $rows = [];
        foreach ($all as $id => $course) {
            if (!in_array($id, $visibleIds, true)) continue;
            if (!empty($filter['status']) && $course['status'] !== $filter['status']) continue;
            if (!empty($filter['category']) && $course['category'] !== $filter['category']) continue;
            if (!empty($filter['keyword']) && !str_contains($course['title'], $filter['keyword'])) continue;
            $rows[] = $course;
        }

        return [
            'total' => count($rows),
            'list' => $rows,
            '_scope_debug' => $scopeInfo,
        ];
    }

    private static function resolveVisibleIds(string $sql, array $params, array $all): array
    {
        $result = [];

        $tenantMatch = [];
        if (preg_match('/\btenant_id\s*=\s*\?/', $sql, $tenantMatch)) {
            $tid = $params[0] ?? null;
            foreach ($all as $id => $c) {
                if (($c['tenant_id'] ?? null) == $tid && $tid !== null) $result[] = $id;
            }
        } elseif (preg_match('/\btenant_id\s+IS\s+NULL/', $sql)) {
            foreach ($all as $id => $c) {
                if ($c['tenant_id'] === null) $result[] = $id;
            }
        } else {
            $result = array_keys($all);
        }

        if (preg_match('/\bdept_id\s+IN\s*\(([^)]+)\)/', $sql, $deptMatch)) {
            $count = substr_count($deptMatch[1], '?');
            $deptIds = [];
            for ($i = 0; $i < $count; $i++) {
                if (isset($params[$i])) $deptIds[] = $params[$i];
            }
            $deptIds = array_slice($params, -$count, $count);
            $result = array_values(array_filter($result, function($id) use ($all, $deptIds) {
                return in_array($all[$id]['dept_id'] ?? null, $deptIds, true);
            }));
        } elseif (preg_match('/\bdept_id\s*=\s*\?/', $sql, $deptMatch)) {
            $deptParam = self::findParamByPosition($sql, $params, 'dept_id');
            $result = array_values(array_filter($result, function($id) use ($all, $deptParam) {
                return ($all[$id]['dept_id'] ?? null) == $deptParam;
            }));
        }

        if (preg_match('/\bowner_id\s+IN\s*\(([^)]+)\)/', $sql, $ownerMatch)) {
            $count = substr_count($ownerMatch[1], '?');
            $ownerIds = array_slice($params, -$count, $count);
            $result = array_values(array_filter($result, function($id) use ($all, $ownerIds) {
                return in_array($all[$id]['owner_id'] ?? null, $ownerIds, true);
            }));
        }

        if (preg_match('/\((?:\bowner_id\b\s*=\s*\?\s+OR\s+\bcreated_by\b\s*=\s*\?)\)/', $sql, $selfMatch)) {
            $uid = end($params);
            $result = array_values(array_filter($result, function($id) use ($all, $uid) {
                return ($all[$id]['owner_id'] ?? null) == $uid
                    || ($all[$id]['created_by'] ?? null) == $uid;
            }));
        }

        if (preg_match('/\b1\s*=\s*0\b/', $sql)) {
            return [];
        }

        return $result;
    }

    private static function findParamByPosition(string $sql, array $params, string $column): mixed
    {
        $pattern = "/\b{$column}\s*=\s*\?/";
        if (!preg_match($pattern, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return end($params);
        }
        $pos = $matches[0][1];
        $beforeSql = substr($sql, 0, $pos);
        $questionCount = substr_count($beforeSql, '?');
        return $params[$questionCount] ?? end($params);
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
