<?php

namespace App\Module\Course\Controller;

use App\Module\Course\Service\CourseService;

class CourseController
{
    private CourseService $courseService;

    public function __construct()
    {
        $this->courseService = new CourseService();
    }

    public function index(array $request): array
    {
        $filter = $request['query'] ?? [];
        return $this->courseService->listCourses($filter);
    }

    public function show(array $request): array
    {
        $id = (int)($request['route_params']['id'] ?? 0);
        return $this->courseService->getCourseDetail($id);
    }

    public function store(array $request): array
    {
        $body = $request['body'] ?? [];
        return $this->courseService->createCourse($body);
    }

    public function update(array $request): array
    {
        $id = (int)($request['route_params']['id'] ?? 0);
        $body = $request['body'] ?? [];
        return $this->courseService->updateCourse($id, $body);
    }

    public function destroy(array $request): array
    {
        $id = (int)($request['route_params']['id'] ?? 0);
        return $this->courseService->deleteCourse($id);
    }

    public function debug(array $request): array
    {
        return $this->courseService->debugTenantQuery();
    }

    public function crossRoleReport(array $request): array
    {
        $body = $request['body'] ?? [];
        $targetRoles = $body['target_roles'] ?? [];
        return $this->courseService->crossRoleViewReport($targetRoles);
    }
}
