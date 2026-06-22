<?php

namespace App\Core\Middleware;

use App\Core\Context\TenantContext;
use App\Core\Enum\RoleType;
use App\Core\Exception\ForbiddenException;
use App\Core\Exception\UnauthorizedException;

class TenantMiddleware
{
    private const TENANT_HEADER = 'X-Tenant-Id';
    private const AUTH_HEADER = 'Authorization';

    public function handle(array $request, callable $next): array
    {
        try {
            $token = $this->extractToken($request);
            $payload = $this->parseToken($token);
            $tenantId = $this->resolveTenantId($request, $payload);

            $this->validateTenantAccess($payload, $tenantId);

            $payload['tenant_id'] = $tenantId;
            $payload['dept_child_ids'] = $this->resolveDeptTree($payload['dept_id'] ?? null);
            $payload['team_member_ids'] = $this->resolveTeamMembers($payload['team_id'] ?? null);

            TenantContext::getInstance()->bootstrap($payload);

            return $next($request);
        } catch (UnauthorizedException $e) {
            return $this->buildErrorResponse($e);
        } catch (ForbiddenException $e) {
            return $this->buildErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse(
                new UnauthorizedException('认证失败: ' . $e->getMessage(), $e)
            );
        }
    }

    private function extractToken(array $request): string
    {
        $headers = $request['headers'] ?? [];
        $token = $headers[strtolower(self::AUTH_HEADER)] ?? $headers['HTTP_AUTHORIZATION'] ?? null;

        if (!$token) {
            throw new UnauthorizedException('缺少认证令牌');
        }

        return $token;
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

    private function resolveTenantId(array $request, array $payload): ?int
    {
        $headers = $request['headers'] ?? [];
        $tenantId = $headers[strtolower(self::TENANT_HEADER)]
            ?? $headers['HTTP_X_TENANT_ID']
            ?? ($payload['tenant_id'] ?? null);

        return $tenantId !== null ? (int)$tenantId : null;
    }

    private function validateTenantAccess(array $payload, ?int $tenantId): void
    {
        $role = $payload['role'] ?? null;
        $isSuperAdmin = $role === RoleType::SUPER_ADMIN->value;

        if (!$isSuperAdmin && $tenantId === null) {
            throw new ForbiddenException('非超级管理员必须指定租户');
        }

        if (!$isSuperAdmin && $tenantId !== null) {
            $userTenantId = isset($payload['tenant_id']) ? (int)$payload['tenant_id'] : 0;
            if ($tenantId !== $userTenantId) {
                throw new ForbiddenException('无权访问该租户数据');
            }
        }
    }

    private function resolveDeptTree(?int $deptId): array
    {
        if ($deptId === null) {
            return [];
        }

        $allDepts = $this->getDepartmentTree();
        $result = [$deptId];
        $this->collectChildren($deptId, $allDepts, $result);
        return $result;
    }

    private function getDepartmentTree(): array
    {
        return [
            1 => ['id' => 1, 'parent_id' => null, 'name' => '总公司'],
            2 => ['id' => 2, 'parent_id' => 1, 'name' => '教学部'],
            3 => ['id' => 3, 'parent_id' => 1, 'name' => '市场部'],
            4 => ['id' => 4, 'parent_id' => 2, 'name' => '语文组'],
            5 => ['id' => 5, 'parent_id' => 2, 'name' => '数学组'],
            6 => ['id' => 6, 'parent_id' => 4, 'name' => '小学语文'],
            7 => ['id' => 7, 'parent_id' => 4, 'name' => '中学语文'],
        ];
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
        if ($teamId === null) {
            return [];
        }

        $teamMap = [
            101 => [101, 202, 203, 204],
            102 => [301, 302, 303],
            103 => [401, 402],
        ];

        return $teamMap[$teamId] ?? [];
    }

    private function buildErrorResponse(\Throwable $e): array
    {
        $code = $e instanceof ForbiddenException ? 403 : 401;
        $errorCode = $e instanceof ForbiddenException ? 'FORBIDDEN' : 'UNAUTHORIZED';

        return [
            'status' => $code,
            'body' => json_encode([
                'code' => $code,
                'error_code' => $errorCode,
                'message' => $e->getMessage(),
                'data' => null,
            ], JSON_UNESCAPED_UNICODE),
        ];
    }
}
