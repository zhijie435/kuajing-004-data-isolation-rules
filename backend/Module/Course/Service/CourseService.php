<?php

namespace App\Module\Course\Service;

use App\Core\Service\DataVisibilityService;
use App\Module\Course\Model\CourseModel;
use App\Core\Context\TenantContext;

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
            throw new \RuntimeException('课程不存在或无权查看');
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
        $ctx = TenantContext::getInstance();

        if (!$ctx->isSuperAdmin() && !$ctx->getTenantId()) {
            throw new \RuntimeException('必须指定所属租户');
        }

        $course = CourseModel::create($data);

        return [
            'message' => '创建成功',
            'course' => $course,
            '_scope_info' => $this->visibility->getScopeSummary(),
        ];
    }

    public function updateCourse(int $id, array $data): array
    {
        $course = CourseModel::findByIdRaw($id);
        if (!$course) {
            throw new \RuntimeException('课程不存在');
        }

        $this->visibility->assertCanModify($course, '课程');

        $updated = CourseModel::update($id, $data);

        return [
            'message' => '更新成功',
            'course' => $updated,
        ];
    }

    public function deleteCourse(int $id): array
    {
        $course = CourseModel::findByIdRaw($id);
        if (!$course) {
            throw new \RuntimeException('课程不存在');
        }

        $this->visibility->assertCanModify($course, '课程');

        $deleted = CourseModel::delete($id);
        if (!$deleted) {
            throw new \RuntimeException('删除失败');
        }

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
