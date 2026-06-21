<?php

namespace App\Core\Middleware;

use App\Core\Context\TenantContext;
use App\Core\Enum\RoleType;
use App\Core\Exception\UnauthorizedException;

class TenantMiddleware
{
    private const TENANT_HEADER = 'X-Tenant-Id';
    private const AUTH_HEADER = 'Authorization';

    public function handle(array $request, callable $next): array
    {
        $headers = $request['headers'] ?? [];
        $token = $headers[strtolower(self::AUTH_HEADER)] ?? $headers['HTTP_AUTHORIZATION'] ?? null;

        if (!$token) {
            return $this->unauthorized('缺少认证令牌');
        }

        try {
            $payload = $this->parseToken($token);
            $tenantId = $headers[strtolower(self::TENANT_HEADER)] ?? $headers['HTTP_X_TENANT_ID'] ?? ($payload['tenant_id'] ?? null);

            if ($payload['role'] !== RoleType::SUPER_ADMIN->value && $tenantId === null) {
                return $this->forbidden('非超级管理员必须指定租户');
            }

            if ($tenantId !== null && $payload['role'] !== RoleType::SUPER_ADMIN->value && (int)$tenantId !== (int)($payload['tenant_id'] ?? 0)) {
                return $this->forbidden('无权访问该租户数据');
            }

            $payload['tenant_id'] = $tenantId !== null ? (int)$tenantId : null;

            $deptTree = $this->resolveDeptTree($payload['dept_id'] ?? null);
            $teamMembers = $this->resolveTeamMembers($payload['team_id'] ?? null);
            $payload['dept_child_ids'] = $deptTree;
            $payload['team_member_ids'] = $teamMembers;

            TenantContext::getInstance()->bootstrap($payload);

            return $next($request);
        } catch (\Throwable $e) {
            return $this->unauthorized('认证失败: ' . $e->getMessage());
        }
    }

    private function parseToken(string $token): array
    {
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new UnauthorizedException('令牌格式无效');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload) {
            throw new UnauthorizedException('令牌解析失败');
        }

        if (!isset($payload['user_id'], $payload['role'])) {
            throw new UnauthorizedException('令牌缺少必要字段');
        }

        return $payload;
    }

    private function resolveDeptTree(?int $deptId): array
    {
        if ($deptId === null) return [];

        $allDepts = [
            1 => ['id' => 1, 'parent_id' => null, 'name' => '总公司'],
            2 => ['id' => 2, 'parent_id' => 1, 'name' => '教学部'],
            3 => ['id' => 3, 'parent_id' => 1, 'name' => '市场部'],
            4 => ['id' => 4, 'parent_id' => 2, 'name' => '语文组'],
            5 => ['id' => 5, 'parent_id' => 2, 'name' => '数学组'],
            6 => ['id' => 6, 'parent_id' => 4, 'name' => '小学语文'],
            7 => ['id' => 7, 'parent_id' => 4, 'name' => '中学语文'],
        ];

        $result = [$deptId];
        $this->collectChildren($deptId, $allDepts, $result);
        return $result;
    }

    private function collectChildren(int $parentId, array $depts, array &$result): void
    {
        foreach ($depts as $dept) {
            if ($dept['parent_id'] === $parentId) {
                $result[] = $dept['id'];
                $this->collectChildren($dept['id'], $depts, $result);
            }
        }
    }

    private function resolveTeamMembers(?int $teamId): array
    {
        if ($teamId === null) return [];

        $teamMap = [
            101 => [101, 202, 203, 204],
            102 => [301, 302, 303],
            103 => [401, 402],
        ];

        return $teamMap[$teamId] ?? [];
    }

    private function unauthorized(string $msg): array
    {
        return [
            'status' => 401,
            'body' => json_encode(['code' => 401, 'message' => $msg, 'data' => null], JSON_UNESCAPED_UNICODE),
        ];
    }

    private function forbidden(string $msg): array
    {
        return [
            'status' => 403,
            'body' => json_encode(['code' => 403, 'message' => $msg, 'data' => null], JSON_UNESCAPED_UNICODE),
        ];
    }
}
