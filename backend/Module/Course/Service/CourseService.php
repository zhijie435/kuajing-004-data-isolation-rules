<?php

namespace App\Module\Course\Service;

use App\Core\Service\DataVisibilityService;
use App\Module\Course\Model\CourseModel;

class CourseService
{
    private DataVisibilityService $visibility;

    public function __construct()
    {
        $this->visibility = new DataVisibilityService();
    }

    public function listCourses(array $filter = []): array
    {
        $result = CourseModel::findAllByFilter($filter);

        foreach ($result['list'] as &$course) {
            $course['_permissions'] = [
                'can_view' => $this->visibility->canViewResource($course),
                'can_edit' => $this->visibility->canModifyResource($course),
                'can_delete' => $this->visibility->canModifyResource($course),
            ];
        }

        return $result;
    }

    public function getCourseDetail(int $id): array
    {
        $course = CourseModel::findById($id);
        if (!$course) {
            throw new \RuntimeException('课程不存在');
        }

        $this->visibility->assertCanView($course, '课程');

        $course['_permissions'] = [
            'can_view' => true,
            'can_edit' => $this->visibility->canModifyResource($course),
            'can_delete' => $this->visibility->canModifyResource($course),
        ];

        return $course;
    }

    public function createCourse(array $data): array
    {
        $ctx = \App\Core\Context\TenantContext::getInstance();

        if (!$ctx->isSuperAdmin() && !$ctx->getTenantId()) {
            throw new \RuntimeException('必须指定所属租户');
        }

        $id = 1100 + mt_rand(1, 9999);
        $course = [
            'id' => $id,
            'tenant_id' => $data['tenant_id'] ?? $ctx->getTenantId(),
            'dept_id' => $data['dept_id'] ?? $ctx->getDeptId(),
            'team_id' => $data['team_id'] ?? $ctx->getTeamId(),
            'owner_id' => $ctx->getUserId(),
            'created_by' => $ctx->getUserId(),
            'title' => $data['title'],
            'category' => $data['category'] ?? '未分类',
            'status' => $data['status'] ?? 'draft',
            'student_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'message' => '创建成功',
            'course' => $course,
            '_scope_info' => $this->visibility->getScopeSummary(),
        ];
    }

    public function updateCourse(int $id, array $data): array
    {
        $course = CourseModel::findById($id);
        if (!$course) {
            throw new \RuntimeException('课程不存在');
        }

        $this->visibility->assertCanModify($course, '课程');

        $updatable = ['title', 'category', 'status'];
        foreach ($updatable as $k) {
            if (isset($data[$k])) {
                $course[$k] = $data[$k];
            }
        }

        return [
            'message' => '更新成功',
            'course' => $course,
        ];
    }

    public function deleteCourse(int $id): array
    {
        $course = CourseModel::findById($id);
        if (!$course) {
            throw new \RuntimeException('课程不存在');
        }

        $this->visibility->assertCanModify($course, '课程');

        return [
            'message' => '删除成功',
            'deleted_id' => $id,
        ];
    }

    public function debugTenantQuery(): array
    {
        return CourseModel::debugQuery();
    }

    public function crossRoleViewReport(array $targetRoles = []): array
    {
        $visibleRoles = $this->visibility->buildCrossRoleFilter($targetRoles);

        $allCourses = CourseModel::findAllByFilter();
        $filtered = [];

        $roleOwnerMap = [
            202 => 'teacher', 203 => 'teacher', 204 => 'teacher',
            301 => 'teacher', 302 => 'teacher', 303 => 'teacher',
            401 => 'team_leader', 402 => 'dept_head',
            501 => 'tenant_admin',
            999 => 'super_admin',
        ];

        foreach ($allCourses['list'] as $c) {
            $ownerRole = $roleOwnerMap[$c['owner_id']] ?? 'student';
            if (in_array($ownerRole, $visibleRoles, true)) {
                $filtered[] = [
                    'course_id' => $c['id'],
                    'title' => $c['title'],
                    'owner_role' => $ownerRole,
                    'visible' => true,
                ];
            }
        }

        return [
            'target_roles' => $targetRoles,
            'visible_roles' => $visibleRoles,
            'visible_course_count' => count($filtered),
            'courses' => $filtered,
        ];
    }
}
