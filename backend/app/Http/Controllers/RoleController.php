<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\DataScopeService;
use App\Enums\DataScopeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $roles = $query->orderBy('id')->paginate($request->input('per_page', 15));

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:sys_role',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'data_scope' => 'required|integer|in:' . implode(',', [
                DataScopeEnum::ALL,
                DataScopeEnum::TENANT,
                DataScopeEnum::DEPARTMENT,
                DataScopeEnum::DEPARTMENT_AND_SUB,
                DataScopeEnum::SELF,
                DataScopeEnum::CUSTOM,
            ]),
            'dept_ids' => 'nullable|array',
            'dept_ids.*' => 'integer|exists:sys_dept,id',
        ]);

        $deptIds = $validated['dept_ids'] ?? [];
        unset($validated['dept_ids']);

        $role = Role::create($validated);

        if (!empty($deptIds) && $validated['data_scope'] === DataScopeEnum::CUSTOM) {
            $role->departments()->sync($deptIds);
        }

        return response()->json([
            'code' => 200,
            'message' => '创建成功',
            'data' => $role->fresh(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'data_scope' => 'sometimes|integer|in:' . implode(',', [
                DataScopeEnum::ALL,
                DataScopeEnum::TENANT,
                DataScopeEnum::DEPARTMENT,
                DataScopeEnum::DEPARTMENT_AND_SUB,
                DataScopeEnum::SELF,
                DataScopeEnum::CUSTOM,
            ]),
            'dept_ids' => 'nullable|array',
            'dept_ids.*' => 'integer|exists:sys_dept,id',
        ]);

        $deptIds = $validated['dept_ids'] ?? null;
        unset($validated['dept_ids']);

        $role->update($validated);

        if ($deptIds !== null) {
            $scope = $validated['data_scope'] ?? $role->data_scope;
            if ($scope === DataScopeEnum::CUSTOM) {
                $role->departments()->sync($deptIds);
            } else {
                $role->departments()->detach();
            }
        }

        return response()->json([
            'code' => 200,
            'message' => '更新成功',
            'data' => $role->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->users()->exists()) {
            return response()->json([
                'code' => 422,
                'message' => '该角色下存在用户，无法删除',
            ], 422);
        }

        $role->departments()->detach();
        $role->delete();

        return response()->json([
            'code' => 200,
            'message' => '删除成功',
        ]);
    }
}
