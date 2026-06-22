<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\TenantContext;
use App\Services\DataScopeService;
use App\Traits\ApiResponseTrait;
use App\Exceptions\TenantIsolationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CourseController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Course::with(['creator:id,nickname', 'department:id,name']);

            if ($request->filled('title')) {
                $query->where('title', 'like', '%' . $request->input('title') . '%');
            }

            if ($request->filled('status')) {
                $query->where('status', $request->boolean('status'));
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            if ($request->filled('dept_id')) {
                $query->where('dept_id', $request->input('dept_id'));
            }

            $perPage = $request->input('per_page', 15);
            $page = $request->input('page', 1);

            $courses = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

            return $this->success($courses, '查询成功', [
                'scope' => DataScopeService::buildFilterParams(),
                'pagination' => [
                    'total' => $courses->total(),
                    'current_page' => $courses->currentPage(),
                    'per_page' => $courses->perPage(),
                    'last_page' => $courses->lastPage(),
                    'from' => $courses->firstItem(),
                    'to' => $courses->lastItem(),
                ],
            ]);
        } catch (TenantIsolationException $e) {
            return $this->tenantError($e);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500, 500, [], '查询失败，请稍后重试');
        }
    }

    public function store(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'nullable|integer',
                'status' => 'boolean',
                'dept_id' => 'nullable|integer',
            ]);

            $user = TenantContext::getUser();
            if (!$user) {
                throw TenantIsolationException::contextUninitialized('Course::store');
            }

            $validated['created_by'] = $user->id;
            $validated['dept_id'] = $validated['dept_id'] ?? $user->dept_id;

            $course = Course::create($validated);
            $course->load(['creator:id,nickname', 'department:id,name']);

            $mutation = $this->mutationResult('create', '课程', $course->id, true, 1);

            DB::commit();

            return $this->success($course, $mutation['user_message'], [
                '_mutation' => $mutation,
                'scope' => DataScopeService::buildFilterParams(),
            ]);
        } catch (TenantIsolationException $e) {
            DB::rollBack();
            return $this->tenantError($e);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->error(
                '数据校验失败',
                422,
                422,
                ['errors' => $e->errors()],
                '表单填写有误，请检查后重试'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500, 500, [], '创建失败，请稍后重试');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $course = Course::with(['creator:id,nickname', 'department:id,name'])->find($id);

            if (!$course) {
                $exists = Course::withoutGlobalScopes()->where('id', $id)->exists();
                if ($exists) {
                    throw TenantIsolationException::dataNotFoundOrDenied('课程', $id);
                }
                return $this->notFoundOrDenied('课程', $id, '查看');
            }

            return $this->success($course);
        } catch (TenantIsolationException $e) {
            return $this->tenantError($e, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500, 500, [], '查询失败');
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'nullable|integer',
                'status' => 'boolean',
            ]);

            $course = Course::find($id);

            if (!$course) {
                $exists = Course::withoutGlobalScopes()->where('id', $id)->exists();
                DB::rollBack();
                if ($exists) {
                    throw TenantIsolationException::dataNotFoundOrDenied('课程', $id);
                }
                return $this->notFoundOrDenied('课程', $id, '编辑');
            }

            $originalData = $course->getOriginal();
            $updated = $course->update($validated);

            $course->load(['creator:id,nickname', 'department:id,name']);

            $changedFields = array_keys($course->getChanges());
            $mutation = $this->mutationResult('update', '课程', $id, $updated, $updated ? 1 : 0, [
                'changed_fields' => $changedFields,
                'original_snapshot' => $this->maskSensitiveFields($originalData),
            ]);

            DB::commit();

            return $this->success($course->fresh(), $mutation['user_message'], [
                '_mutation' => $mutation,
                'scope' => DataScopeService::buildFilterParams(),
            ]);
        } catch (TenantIsolationException $e) {
            DB::rollBack();
            return $this->tenantError($e);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->error(
                '数据校验失败',
                422,
                422,
                ['errors' => $e->errors()],
                '表单填写有误，请检查后重试'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500, 500, [], '更新失败，请稍后重试');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $course = Course::find($id);

            if (!$course) {
                $exists = Course::withoutGlobalScopes()->where('id', $id)->exists();
                DB::rollBack();
                if ($exists) {
                    throw TenantIsolationException::dataNotFoundOrDenied('课程', $id);
                }
                return $this->notFoundOrDenied('课程', $id, '删除');
            }

            $snapshot = $course->only(['id', 'title', 'tenant_id', 'dept_id', 'created_by']);
            $deleted = $course->delete();

            $mutation = $this->mutationResult('delete', '课程', $id, $deleted, $deleted ? 1 : 0, [
                'deleted_snapshot' => $snapshot,
            ]);

            DB::commit();

            return $this->success(null, $mutation['user_message'], [
                '_mutation' => $mutation,
                'scope' => DataScopeService::buildFilterParams(),
            ]);
        } catch (TenantIsolationException $e) {
            DB::rollBack();
            return $this->tenantError($e);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500, 500, [], '删除失败，请稍后重试');
        }
    }

    public function all(Request $request): JsonResponse
    {
        try {
            $query = Course::with(['creator:id,nickname', 'department:id,name'])->orderBy('id', 'desc');

            if ($request->filled('limit')) {
                $query->limit((int) $request->input('limit', 100));
            }

            $courses = $query->get();

            return $this->success($courses, '查询成功', [
                'scope' => DataScopeService::buildFilterParams(),
                'total' => $courses->count(),
            ]);
        } catch (TenantIsolationException $e) {
            return $this->tenantError($e);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500, 500, [], '查询失败');
        }
    }

    protected function maskSensitiveFields(array $data): array
    {
        unset($data['password'], $data['remember_token']);
        return $data;
    }
}
