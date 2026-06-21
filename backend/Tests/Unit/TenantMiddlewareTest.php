<?php

namespace App\Tests\Unit;

use App\Tests\TestCase;
use App\Core\Middleware\TenantMiddleware;
use App\Core\Context\TenantContext;
use App\Core\Enum\RoleType;
use App\Core\Exception\UnauthorizedException;
use App\Core\Exception\ForbiddenException;

class TenantMiddlewareTest extends TestCase
{
    private TenantMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TenantMiddleware();
    }

    public function testTokenExtractionFromAuthorizationHeader(): void
    {
        $token = $this->generateJwtToken(['user_id' => 1, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Token extraction should succeed');
    }

    public function testTokenExtractionFromHttpAuthorizationHeader(): void
    {
        $token = $this->generateJwtToken(['user_id' => 1, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Token extraction from HTTP_AUTHORIZATION should succeed');
    }

    public function testMissingTokenThrowsUnauthorized(): void
    {
        $request = ['headers' => []];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(401, $response['status'], 'Missing token should return 401');
        $this->assertStringContainsString('缺少认证令牌', $response['body'], 'Should have missing token message');
    }

    public function testInvalidTokenFormatThrowsUnauthorized(): void
    {
        $request = [
            'headers' => [
                'authorization' => 'Bearer invalid.token',
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(401, $response['status'], 'Invalid token format should return 401');
        $this->assertStringContainsString('令牌格式无效', $response['body'], 'Should have invalid format message');
    }

    public function testTokenWithMissingUserIdThrowsUnauthorized(): void
    {
        $token = $this->generateJwtToken(['role' => 'teacher']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(401, $response['status'], 'Token missing user_id should return 401');
    }

    public function testTokenWithMissingRoleThrowsUnauthorized(): void
    {
        $token = $this->generateJwtToken(['user_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(401, $response['status'], 'Token missing role should return 401');
    }

    public function testNonSuperAdminWithoutTenantThrowsForbidden(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(403, $response['status'], 'Non-super-admin without tenant should return 403');
        $this->assertStringContainsString('非超级管理员必须指定租户', $response['body']);
    }

    public function testNonSuperAdminWithDifferentTenantThrowsForbidden(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 2,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(403, $response['status'], 'Accessing different tenant should return 403');
        $this->assertStringContainsString('无权访问该租户数据', $response['body']);
    }

    public function testNonSuperAdminWithSameTenantSucceeds(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 1,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Same tenant access should succeed');
    }

    public function testSuperAdminWithoutTenantSucceeds(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Super admin without tenant should succeed');
        $this->assertNull(TenantContext::getInstance()->getTenantId(), 'Super admin tenant_id should be null');
    }

    public function testSuperAdminWithSpecificTenantSucceeds(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 2,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Super admin with specific tenant should succeed');
        $this->assertEquals(2, TenantContext::getInstance()->getTenantId(), 'Tenant ID should be set to 2');
    }

    public function testTenantIdFromHeaderTakesPrecedenceOverToken(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 3,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(3, TenantContext::getInstance()->getTenantId(), 'Header tenant_id should take precedence');
    }

    public function testTenantIdFromTokenWhenNoHeader(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 5]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(5, TenantContext::getInstance()->getTenantId(), 'Token tenant_id should be used when no header');
    }

    public function testContextBootstrapSetsCorrectValues(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'username' => 'teacher_zhang',
            'dept_id' => 2,
            'team_id' => 101,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $ctx = TenantContext::getInstance();
        $this->assertEquals(202, $ctx->getUserId());
        $this->assertEquals(RoleType::TEACHER, $ctx->getRole());
        $this->assertEquals(1, $ctx->getTenantId());
        $this->assertEquals('teacher_zhang', $ctx->getUsername());
        $this->assertEquals(2, $ctx->getDeptId());
        $this->assertEquals(101, $ctx->getTeamId());
    }

    public function testDeptTreeResolutionForLeafNode(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'dept_id' => 6,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $deptChildIds = TenantContext::getInstance()->getDeptChildIds();
        $this->assertEquals([6], $deptChildIds, 'Leaf dept should have only itself');
    }

    public function testDeptTreeResolutionForParentNode(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 4,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $deptChildIds = TenantContext::getInstance()->getDeptChildIds();
        $this->assertContains(4, $deptChildIds, 'Should contain parent dept');
        $this->assertContains(6, $deptChildIds, 'Should contain child dept 6');
        $this->assertContains(7, $deptChildIds, 'Should contain child dept 7');
        $this->assertNotContains(2, $deptChildIds, 'Should not contain grandparent dept');
    }

    public function testDeptTreeResolutionForRootNode(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
            'dept_id' => 1,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $deptChildIds = TenantContext::getInstance()->getDeptChildIds();
        $expected = [1, 2, 3, 4, 5, 6, 7];
        foreach ($expected as $id) {
            $this->assertContains($id, $deptChildIds, "Should contain dept {$id}");
        }
    }

    public function testNullDeptIdReturnsEmptyArray(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $deptChildIds = TenantContext::getInstance()->getDeptChildIds();
        $this->assertEmpty($deptChildIds, 'Null dept_id should return empty array');
    }

    public function testTeamMemberResolution(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $teamMemberIds = TenantContext::getInstance()->getTeamMemberIds();
        $this->assertEquals([101, 202, 203, 204], $teamMemberIds, 'Team members should be resolved');
    }

    public function testNullTeamIdReturnsEmptyArray(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $teamMemberIds = TenantContext::getInstance()->getTeamMemberIds();
        $this->assertEmpty($teamMemberIds, 'Null team_id should return empty array');
    }

    public function testUnknownTeamIdReturnsEmptyArray(): void
    {
        $token = $this->generateJwtToken([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'team_id' => 999,
        ]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $teamMemberIds = TenantContext::getInstance()->getTeamMemberIds();
        $this->assertEmpty($teamMemberIds, 'Unknown team_id should return empty array');
    }

    public function testNextHandlerIsCalledWithOriginalRequest(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
            'path' => '/api/courses',
            'method' => 'GET',
        ];

        $receivedRequest = null;
        $this->middleware->handle($request, function ($req) use (&$receivedRequest) {
            $receivedRequest = $req;
            return ['status' => 200, 'body' => 'OK'];
        });

        $this->assertNotNull($receivedRequest, 'Next handler should receive request');
        $this->assertEquals('/api/courses', $receivedRequest['path'], 'Request path should be preserved');
        $this->assertEquals('GET', $receivedRequest['method'], 'Request method should be preserved');
    }

    public function testSuperAdminCanAccessTenant1(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 1,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Super admin should access any tenant');
        $this->assertEquals(1, TenantContext::getInstance()->getTenantId());
    }

    public function testSuperAdminCanAccessTenant2(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 2,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Super admin should access any tenant');
        $this->assertEquals(2, TenantContext::getInstance()->getTenantId());
    }

    public function testTenantAdminCannotAccessOtherTenant(): void
    {
        $token = $this->generateJwtToken(['user_id' => 101, 'role' => 'tenant_admin', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => 2,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(403, $response['status'], 'Tenant admin should not access other tenant');
    }

    public function testDefaultDataScopeIsSetForRole(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(5, TenantContext::getInstance()->getDataScope()->value, 'Teacher default scope should be SELF (5)');
    }

    public function testSuperAdminDefaultScopeIsAll(): void
    {
        $token = $this->generateJwtToken(['user_id' => 999, 'role' => 'super_admin']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(1, TenantContext::getInstance()->getDataScope()->value, 'Super admin default scope should be ALL (1)');
    }

    public function testTenantAdminDefaultScopeIsTenant(): void
    {
        $token = $this->generateJwtToken(['user_id' => 101, 'role' => 'tenant_admin', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(2, TenantContext::getInstance()->getDataScope()->value, 'Tenant admin default scope should be TENANT (2)');
    }

    public function testDeptHeadDefaultScopeIsDepartment(): void
    {
        $token = $this->generateJwtToken(['user_id' => 102, 'role' => 'dept_head', 'tenant_id' => 1, 'dept_id' => 4]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(3, TenantContext::getInstance()->getDataScope()->value, 'Dept head default scope should be DEPARTMENT (3)');
    }

    public function testTeamLeaderDefaultScopeIsTeam(): void
    {
        $token = $this->generateJwtToken(['user_id' => 201, 'role' => 'team_leader', 'tenant_id' => 1, 'team_id' => 101]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(4, TenantContext::getInstance()->getDataScope()->value, 'Team leader default scope should be TEAM (4)');
    }

    public function testErrorResponseFormatForUnauthorized(): void
    {
        $request = ['headers' => []];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);
        $body = json_decode($response['body'], true);

        $this->assertEquals(401, $body['code']);
        $this->assertEquals('UNAUTHORIZED', $body['error_code']);
        $this->assertNotNull($body['message']);
        $this->assertNull($body['data']);
    }

    public function testErrorResponseFormatForForbidden(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher']);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);
        $body = json_decode($response['body'], true);

        $this->assertEquals(403, $body['code']);
        $this->assertEquals('FORBIDDEN', $body['error_code']);
        $this->assertNotNull($body['message']);
        $this->assertNull($body['data']);
    }

    public function testTokenWithoutBearerPrefix(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(200, $response['status'], 'Token without Bearer prefix should still work');
    }

    public function testMalformedJsonInPayload(): void
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = 'invalid_base64_payload';
        $signature = base64_encode('test');
        $token = "{$header}.{$payload}.{$signature}";

        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
            ],
        ];

        $response = $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(401, $response['status'], 'Malformed payload should return 401');
    }

    public function testTenantIdFromStringHeader(): void
    {
        $token = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request = [
            'headers' => [
                'authorization' => 'Bearer ' . $token,
                'x-tenant-id' => '1',
            ],
        ];

        $this->middleware->handle($request, fn($req) => ['status' => 200, 'body' => 'OK']);

        $this->assertEquals(1, TenantContext::getInstance()->getTenantId(), 'String tenant_id should be converted to int');
    }

    public function testContextIsResetBetweenRequests(): void
    {
        $token1 = $this->generateJwtToken(['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1]);
        $request1 = [
            'headers' => [
                'authorization' => 'Bearer ' . $token1,
            ],
        ];

        $this->middleware->handle($request1, fn($req) => ['status' => 200, 'body' => 'OK']);
        $this->assertEquals(202, TenantContext::getInstance()->getUserId());

        TenantContext::getInstance()->reset();

        $token2 = $this->generateJwtToken(['user_id' => 203, 'role' => 'teacher', 'tenant_id' => 1]);
        $request2 = [
            'headers' => [
                'authorization' => 'Bearer ' . $token2,
            ],
        ];

        $this->middleware->handle($request2, fn($req) => ['status' => 200, 'body' => 'OK']);
        $this->assertEquals(203, TenantContext::getInstance()->getUserId());
    }
}
