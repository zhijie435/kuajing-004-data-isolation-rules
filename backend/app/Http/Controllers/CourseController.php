<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\TenantContext;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::query();

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->input('title') . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $perPage = $request->input('per_page', 15);
        $courses = $query->orderBy('id', 'desc')->paginate($perPage);

        $courses->getCollection()->transform(function ($course) {
            $course->append('tenant_name');
            return $course;
        });

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $courses,
            'scope' => DataScopeService::buildFilterParams(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:course_categories,id',
            'status' => 'boolean',
        ]);

        $user = TenantContext::getUser();
        $validated['created_by'] = $user->id;
        $validated['dept_id'] = $user->dept_id;

        $course = Course::create($validated);

        $course->refresh();

        return response()->json([
            'code' => 200,
            'message' => '创建成功',
            'data' => $course->fresh(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $course,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:course_categories,id',
            'status' => 'boolean',
        ]);

        $course->update($validated);

        return response()->json([
            'code' => 200,
            'message' => '更新成功',
            'data' => $course->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json([
            'code' => 200,
            'message' => '删除成功',
        ]);
    }

    public function all(): JsonResponse
    {
        $courses = Course::orderBy('id', 'desc')->get();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $courses,
            'scope' => DataScopeService::buildFilterParams(),
        ]);
    }
}
