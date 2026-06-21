<?php

namespace App\Tests\Unit;

use App\Tests\TestCase;
use App\Core\Service\DataVisibilityService;
use App\Core\Context\TenantContext;
use App\Core\Enum\RoleType;
use App\Core\Enum\DataScopeLevel;
use App\Core\Exception\ForbiddenException;

class DataVisibilityServiceTest extends TestCase
{
    private DataVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataVisibilityService();
    }

    private function setupContext(array $payload): void
    {
        TenantContext::getInstance()->bootstrap($payload);
    }

    public function testCanViewResourceAllScope(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
            'data_scope' => 1,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canViewResource($resource), 'ALL scope should see all resources');
    }

    public function testCanViewResourceTenantScopeSameTenant(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canViewResource($resource), 'TENANT scope should see same tenant resources');
    }

    public function testCanViewResourceTenantScopeDifferentTenant(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 2, 'owner_id' => 401];
        $this->assertFalse($this->service->canViewResource($resource), 'TENANT scope should not see other tenant resources');
    }

    public function testCanViewResourceDepartmentScopeInDept(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 4,
            'dept_child_ids' => [4, 6, 7],
            'data_scope' => 3,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'dept_id' => 6, 'owner_id' => 204];
        $this->assertTrue($this->service->canViewResource($resource), 'DEPT scope should see child dept resources');
    }

    public function testCanViewResourceDepartmentScopeOutsideDept(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 4,
            'dept_child_ids' => [4, 6, 7],
            'data_scope' => 3,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'dept_id' => 5, 'owner_id' => 301];
        $this->assertFalse($this->service->canViewResource($resource), 'DEPT scope should not see outside dept resources');
    }

    public function testCanViewResourceTeamScopeInTeam(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 4,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canViewResource($resource), 'TEAM scope should see team member resources');
    }

    public function testCanViewResourceTeamScopeOutsideTeam(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 4,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 301];
        $this->assertFalse($this->service->canViewResource($resource), 'TEAM scope should not see outside team resources');
    }

    public function testCanViewResourceSelfScopeAsOwner(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canViewResource($resource), 'SELF scope should see owned resources');
    }

    public function testCanViewResourceSelfScopeAsCreator(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'created_by' => 202, 'owner_id' => 203];
        $this->assertTrue($this->service->canViewResource($resource), 'SELF scope should see created resources');
    }

    public function testCanViewResourceSelfScopeNotOwner(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 203, 'created_by' => 203];
        $this->assertFalse($this->service->canViewResource($resource), 'SELF scope should not see others resources');
    }

    public function testCanModifyResourceSuperAdmin(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
            'data_scope' => 1,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 2, 'owner_id' => 401];
        $this->assertTrue($this->service->canModifyResource($resource), 'Super admin can modify any resource');
    }

    public function testCanModifyResourceTenantAdmin(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canModifyResource($resource), 'Tenant admin can modify tenant resources');
    }

    public function testCanModifyResourceDeptHead(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 4,
            'dept_child_ids' => [4, 6, 7],
            'data_scope' => 3,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'dept_id' => 6, 'owner_id' => 204];
        $this->assertTrue($this->service->canModifyResource($resource), 'Dept head can modify dept resources');
    }

    public function testCanModifyResourceTeamLeaderOwnTeam(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 4,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canModifyResource($resource), 'Team leader can modify team member resources');
    }

    public function testCanModifyResourceTeamLeaderOutsideTeam(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 4,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 301];
        $this->assertFalse($this->service->canModifyResource($resource), 'Team leader cannot modify outside team');
    }

    public function testCanModifyResourceTeacherOwnResource(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertTrue($this->service->canModifyResource($resource), 'Teacher can modify own resources');
    }

    public function testCanModifyResourceTeacherOthersResource(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 203];
        $this->assertFalse($this->service->canModifyResource($resource), 'Teacher cannot modify others resources');
    }

    public function testCanModifyResourceNotViewable(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 2, 'owner_id' => 203, 'created_by' => 203];
        $this->assertFalse($this->service->canModifyResource($resource), 'Cannot modify resource that is not viewable');
    }

    public function testGetAvailableScopesSuperAdmin(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $scopes = $this->service->getAvailableScopes();
        $scopeValues = array_column($scopes, 'value');

        $this->assertContains(1, $scopeValues, 'Super admin should have ALL scope');
        $this->assertContains(2, $scopeValues, 'Super admin should have TENANT scope');
        $this->assertContains(3, $scopeValues, 'Super admin should have DEPARTMENT scope');
        $this->assertContains(4, $scopeValues, 'Super admin should have TEAM scope');
        $this->assertContains(5, $scopeValues, 'Super admin should have SELF scope');
    }

    public function testGetAvailableScopesTenantAdmin(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
        ]);

        $scopes = $this->service->getAvailableScopes();
        $scopeValues = array_column($scopes, 'value');

        $this->assertNotContains(1, $scopeValues, 'Tenant admin should not have ALL scope');
        $this->assertContains(2, $scopeValues, 'Tenant admin should have TENANT scope');
        $this->assertContains(3, $scopeValues, 'Tenant admin should have DEPARTMENT scope');
        $this->assertContains(4, $scopeValues, 'Tenant admin should have TEAM scope');
        $this->assertContains(5, $scopeValues, 'Tenant admin should have SELF scope');
    }

    public function testGetAvailableScopesTeacher(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);

        $scopes = $this->service->getAvailableScopes();
        $scopeValues = array_column($scopes, 'value');

        $this->assertEquals([5], $scopeValues, 'Teacher should only have SELF scope');
    }

    public function testSwitchScopeAllowed(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
        ]);

        $result = $this->service->switchScope(DataScopeLevel::DEPARTMENT);

        $this->assertEquals(3, $result['current_scope'], 'Scope should be switched to DEPARTMENT');
        $this->assertEquals(3, TenantContext::getInstance()->getDataScope()->value, 'Context scope should be updated');
    }

    public function testSwitchScopeWiderThanAllowedThrowsForbidden(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);

        $this->expectException(ForbiddenException::class, function () {
            $this->service->switchScope(DataScopeLevel::TENANT);
        }, 'Teacher cannot switch to TENANT scope');
    }

    public function testSwitchScopeSameScopeAllowed(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);

        $result = $this->service->switchScope(DataScopeLevel::SELF);

        $this->assertEquals(5, $result['current_scope'], 'Switching to same scope should work');
    }

    public function testBuildCrossRoleFilterSuperAdmin(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $visibleRoles = $this->service->buildCrossRoleFilter();

        $this->assertContains('super_admin', $visibleRoles);
        $this->assertContains('tenant_admin', $visibleRoles);
        $this->assertContains('dept_head', $visibleRoles);
        $this->assertContains('team_leader', $visibleRoles);
        $this->assertContains('teacher', $visibleRoles);
        $this->assertContains('student', $visibleRoles);
    }

    public function testBuildCrossRoleFilterTenantAdmin(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
        ]);

        $visibleRoles = $this->service->buildCrossRoleFilter();

        $this->assertNotContains('super_admin', $visibleRoles, 'Tenant admin should not see super admin');
        $this->assertContains('tenant_admin', $visibleRoles);
        $this->assertContains('dept_head', $visibleRoles);
        $this->assertContains('teacher', $visibleRoles);
        $this->assertContains('student', $visibleRoles);
    }

    public function testBuildCrossRoleFilterTeacher(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);

        $visibleRoles = $this->service->buildCrossRoleFilter();

        $this->assertNotContains('super_admin', $visibleRoles);
        $this->assertNotContains('tenant_admin', $visibleRoles);
        $this->assertNotContains('dept_head', $visibleRoles);
        $this->assertNotContains('team_leader', $visibleRoles);
        $this->assertContains('teacher', $visibleRoles);
        $this->assertContains('student', $visibleRoles);
    }

    public function testBuildCrossRoleFilterWithTargetRoles(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
        ]);

        $visibleRoles = $this->service->buildCrossRoleFilter(['teacher', 'student', 'super_admin']);

        $this->assertContains('teacher', $visibleRoles);
        $this->assertContains('student', $visibleRoles);
        $this->assertNotContains('super_admin', $visibleRoles);
        $this->assertNotContains('tenant_admin', $visibleRoles);
    }

    public function testFilterResourcesByRoles(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'owner_id' => 202, 'tenant_id' => 1],
            ['id' => 2, 'title' => 'Course 2', 'owner_id' => 101, 'tenant_id' => 1],
            ['id' => 3, 'title' => 'Course 3', 'owner_id' => 999, 'tenant_id' => 1],
            ['id' => 4, 'title' => 'Course 4', 'owner_id' => 501, 'tenant_id' => 1],
            ['id' => 5, 'title' => 'Course 5', 'owner_id' => 201, 'tenant_id' => 1],
        ];

        $filtered = $this->service->filterResourcesByRoles($resources);

        $filteredIds = array_column($filtered, 'id');
        $this->assertContains(1, $filteredIds, 'Should see teacher course (202=teacher level 20)');
        $this->assertNotContains(2, $filteredIds, 'Should NOT see tenant admin course (101=tenant_admin level 80 > 60)');
        $this->assertNotContains(3, $filteredIds, 'Should not see super admin course (999=super_admin level 100 > 60)');
        $this->assertContains(4, $filteredIds, 'Should see student course (501=student level 10)');
        $this->assertContains(5, $filteredIds, 'Should see team leader course (201=team_leader level 40)');
    }

    public function testExportCrossRoleAuditSuperAdminHealthy(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
            ['id' => 2, 'title' => 'Course 2', 'tenant_id' => 2, 'owner_id' => 401],
        ];

        $audit = $this->service->exportCrossRoleAudit($resources, 'course');

        $this->assertEquals('healthy', $audit['summary']['overall_status']);
        $this->assertEquals(0, $audit['summary']['error_count']);
        $this->assertEquals(0, $audit['summary']['anomaly_count']);
    }

    public function testExportCrossRoleAuditTenantAdminNoCrossTenantLeak(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
            ['id' => 2, 'title' => 'Course 2', 'tenant_id' => 2, 'owner_id' => 401],
        ];

        $audit = $this->service->exportCrossRoleAudit($resources, 'course');

        $this->assertEquals('healthy', $audit['summary']['overall_status']);
    }

    public function testExportCrossRoleAuditWithScopeMismatch(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
        ];

        $audit = $this->service->exportCrossRoleAudit($resources, 'course');

        $this->assertTrue($audit['context']['scope_mismatch'], 'Should detect scope mismatch');
        $this->assertEquals('warning', $audit['summary']['overall_status']);
    }

    public function testApplyAuditWriteBackCorrectsScope(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $result = $this->service->applyAuditWriteBack();

        $this->assertTrue($result['corrected'], 'Should correct scope mismatch');
        $this->assertEquals(5, $result['corrected_scope'], 'Should correct to SELF scope');
        $this->assertEquals(5, TenantContext::getInstance()->getDataScope()->value, 'Context should be updated');
        $this->assertTrue(TenantContext::getInstance()->isScopeCorrected(), 'Scope corrected flag should be set');
    }

    public function testApplyAuditWriteBackNoCorrectionNeeded(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $result = $this->service->applyAuditWriteBack();

        $this->assertFalse($result['corrected'], 'Should not correct when scope matches');
        $this->assertEquals('already_correct', $result['reason']);
    }

    public function testApplyAuditWriteBackNoRole(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'tenant_id' => null,
        ]);

        $result = $this->service->applyAuditWriteBack();

        $this->assertFalse($result['corrected'], 'Should not correct when no role');
        $this->assertEquals('no_role', $result['reason']);
    }

    public function testApplyCrossRoleAuditFixWithScopeMismatch(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
        ];

        $auditResult = $this->service->exportCrossRoleAudit($resources, 'course');
        $fixResult = $this->service->applyCrossRoleAuditFix($auditResult);

        $this->assertTrue($fixResult['scope_fix']['corrected'], 'Should fix scope mismatch');
        $this->assertEquals(5, $fixResult['scope_fix']['corrected_scope'], 'Should correct to SELF');
        $this->assertNotNull($fixResult['re_audit_summary'], 'Should have re-audit summary');
        $this->assertEquals('healthy', $fixResult['re_audit_summary']['overall_status'], 'Re-audit should be healthy');
    }

    public function testApplyCrossRoleAuditFixNoIssues(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
        ];

        $auditResult = $this->service->exportCrossRoleAudit($resources, 'course');
        $fixResult = $this->service->applyCrossRoleAuditFix($auditResult);

        $this->assertEquals(0, $fixResult['total_fixes'], 'No fixes should be applied');
        $this->assertNull($fixResult['re_audit_summary'], 'No re-audit needed');
    }

    public function testStatusClosedLoopAuditFixReaudit(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 101,
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 1,
        ]);

        $resources = [
            ['id' => 1, 'title' => 'Course 1', 'tenant_id' => 1, 'owner_id' => 202],
            ['id' => 2, 'title' => 'Course 2', 'tenant_id' => 1, 'owner_id' => 301],
        ];

        $audit1 = $this->service->exportCrossRoleAudit($resources, 'course');
        $this->assertEquals('error', $audit1['summary']['overall_status'], 'Initial audit should have errors due to visibility mismatch');
        $this->assertTrue($audit1['context']['scope_mismatch'], 'Should detect scope mismatch');
        $this->assertTrue($audit1['summary']['error_count'] > 0, 'Should have visibility mismatch errors');

        $fixResult = $this->service->applyCrossRoleAuditFix($audit1);
        $this->assertTrue($fixResult['scope_fix']['corrected'], 'Should fix scope mismatch');
        $this->assertEquals(4, $fixResult['scope_fix']['corrected_scope'], 'Should correct to TEAM scope');

        $audit2 = $this->service->exportCrossRoleAudit($resources, 'course');
        $this->assertEquals('healthy', $audit2['summary']['overall_status'], 'After fix, audit should be healthy');
        $this->assertFalse($audit2['context']['scope_mismatch'], 'No more scope mismatch');
        $this->assertEquals(0, $audit2['summary']['error_count'], 'No more errors after fix');
    }

    public function testAssertCanViewThrowsForbiddenWhenDenied(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 203];

        $this->expectException(ForbiddenException::class, function () use ($resource) {
            $this->service->assertCanView($resource, '课程');
        }, 'Should throw ForbiddenException when cannot view');
    }

    public function testAssertCanViewPassesWhenAllowed(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];

        $exception = null;
        try {
            $this->service->assertCanView($resource, '课程');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Should not throw exception when can view');
    }

    public function testAssertCanModifyThrowsForbiddenWhenDenied(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 203];

        $this->expectException(ForbiddenException::class, function () use ($resource) {
            $this->service->assertCanModify($resource, '课程');
        }, 'Should throw ForbiddenException when cannot modify');
    }

    public function testAssertCanModifyPassesWhenAllowed(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 202];

        $exception = null;
        try {
            $this->service->assertCanModify($resource, '课程');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'Should not throw exception when can modify');
    }

    public function testGetScopeSummary(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'username' => 'dept_chinese',
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 4,
            'team_id' => 101,
            'dept_child_ids' => [4, 6, 7],
            'team_member_ids' => [101, 202, 203, 204],
            'data_scope' => 3,
        ]);

        $summary = $this->service->getScopeSummary();

        $this->assertEquals(1, $summary['tenant']['id']);
        $this->assertEquals('single_tenant', $summary['tenant']['mode']);
        $this->assertEquals(102, $summary['user']['id']);
        $this->assertEquals('dept_head', $summary['user']['role']);
        $this->assertEquals(3, $summary['data_scope']['current']);
        $this->assertEquals(4, $summary['org']['dept_id']);
        $this->assertEquals([4, 6, 7], $summary['org']['dept_child_ids']);
    }

    public function testGetScopeSummarySuperAdminAllTenants(): void
    {
        $this->setupContext([
            'user_id' => 999,
            'username' => 'super_admin',
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $summary = $this->service->getScopeSummary();

        $this->assertNull($summary['tenant']['id']);
        $this->assertEquals('all_tenants', $summary['tenant']['mode']);
    }

    public function testGetOwnerRoleById(): void
    {
        $this->assertEquals('super_admin', $this->service->getOwnerRoleById(999));
        $this->assertEquals('tenant_admin', $this->service->getOwnerRoleById(101));
        $this->assertEquals('dept_head', $this->service->getOwnerRoleById(102));
        $this->assertEquals('team_leader', $this->service->getOwnerRoleById(201));
        $this->assertEquals('teacher', $this->service->getOwnerRoleById(202));
        $this->assertEquals('student', $this->service->getOwnerRoleById(501));
        $this->assertEquals('student', $this->service->getOwnerRoleById(9999));
        $this->assertEquals('student', $this->service->getOwnerRoleById(null));
    }

    public function testTeamScopeFallsBackToSelfWhenNoMembers(): void
    {
        $this->setupContext([
            'user_id' => 201,
            'role' => 'team_leader',
            'tenant_id' => 1,
            'team_id' => 999,
            'team_member_ids' => [],
            'data_scope' => 4,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'owner_id' => 201];
        $this->assertTrue($this->service->canViewResource($resource), 'Should see own resource when no team members');

        $resource2 = ['id' => 2, 'tenant_id' => 1, 'owner_id' => 202];
        $this->assertFalse($this->service->canViewResource($resource2), 'Should not see others when no team members');
    }

    public function testDeptScopeFallsBackToSingleDeptWhenNoChildren(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => 6,
            'dept_child_ids' => [],
            'data_scope' => 3,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'dept_id' => 6, 'owner_id' => 204];
        $this->assertTrue($this->service->canViewResource($resource), 'Should see same dept resources');

        $resource2 = ['id' => 2, 'tenant_id' => 1, 'dept_id' => 7, 'owner_id' => 203];
        $this->assertFalse($this->service->canViewResource($resource2), 'Should not see other dept resources');
    }

    public function testDeptScopeReturnsEmptyWhenNoDeptId(): void
    {
        $this->setupContext([
            'user_id' => 102,
            'role' => 'dept_head',
            'tenant_id' => 1,
            'dept_id' => null,
            'dept_child_ids' => [],
            'data_scope' => 3,
        ]);

        $resource = ['id' => 1, 'tenant_id' => 1, 'dept_id' => 6, 'owner_id' => 204];
        $this->assertFalse($this->service->canViewResource($resource), 'Should not see anything when no dept_id');
    }

    public function testContextToArray(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'username' => 'teacher_zhang',
            'role' => 'teacher',
            'tenant_id' => 1,
            'dept_id' => 2,
            'team_id' => 101,
            'data_scope' => 5,
        ]);

        $array = TenantContext::getInstance()->toArray();

        $this->assertEquals(1, $array['tenant_id']);
        $this->assertEquals(202, $array['user_id']);
        $this->assertEquals('teacher_zhang', $array['username']);
        $this->assertEquals('teacher', $array['role']);
        $this->assertEquals(5, $array['data_scope']);
        $this->assertEquals(2, $array['dept_id']);
        $this->assertEquals(101, $array['team_id']);
    }

    public function testLastAuditScopeTracking(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $this->assertNull(TenantContext::getInstance()->getLastAuditScope());
        $this->assertFalse(TenantContext::getInstance()->isScopeCorrected());

        $this->service->applyAuditWriteBack();

        $this->assertEquals(2, TenantContext::getInstance()->getLastAuditScope()?->value);
        $this->assertTrue(TenantContext::getInstance()->isScopeCorrected());
    }

    public function testResetClearsAllState(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        $this->service->applyAuditWriteBack();

        $this->assertNotNull(TenantContext::getInstance()->getUserId());
        $this->assertTrue(TenantContext::getInstance()->isScopeCorrected());

        TenantContext::getInstance()->reset();

        $this->assertNull(TenantContext::getInstance()->getUserId());
        $this->assertNull(TenantContext::getInstance()->getLastAuditScope());
        $this->assertFalse(TenantContext::getInstance()->isScopeCorrected());
    }

    public function testSetDataScopePreventsWidening(): void
    {
        $this->setupContext([
            'user_id' => 202,
            'role' => 'teacher',
            'tenant_id' => 1,
            'data_scope' => 5,
        ]);

        $this->expectException(ForbiddenException::class, function () {
            TenantContext::getInstance()->setDataScope(DataScopeLevel::TENANT);
        }, 'Should not allow widening scope');
    }

    public function testSetDataScopeAllowsNarrowing(): void
    {
        $this->setupContext([
            'user_id' => 101,
            'role' => 'tenant_admin',
            'tenant_id' => 1,
            'data_scope' => 2,
        ]);

        TenantContext::getInstance()->setDataScope(DataScopeLevel::SELF);
        $this->assertEquals(5, TenantContext::getInstance()->getDataScope()->value);
    }
}
