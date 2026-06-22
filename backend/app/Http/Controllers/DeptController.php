<?php

namespace App\Http\Controllers;

use App\Models\Dept;
use App\Services\TenantContext;
use App\Services\DataScopeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dept::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $depts = $query->orderBy('sort')->get()->toTree();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $depts,
        ]);
    }

    public function tree(): JsonResponse
    {
        $depts = Dept::orderBy('sort')->get()->toTree();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $depts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:sys_dept,id',
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:sys_dept',
            'sort' => 'nullable|integer',
        ]);

        $dept = Dept::create($validated);
        $dept->refresh();

        return response()->json([
            'code' => 200,
            'message' => '创建成功',
            'data' => $dept->fresh(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Dept::findOrFail($id);

        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:sys_dept,id',
            'name' => 'sometimes|string|max:100',
            'code' => 'nullable|string|max:50|unique:sys_dept,code,' . $id,
            'sort' => 'nullable|integer',
        ]);

        $dept->update($validated);

        return response()->json([
            'code' => 200,
            'message' => '更新成功',
            'data' => $dept->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $dept = Dept::findOrFail($id);

        if ($dept->children()->exists()) {
            return response()->json([
                'code' => 422,
                'message' => '该部门下存在子部门，无法删除',
            ], 422);
        }

        if ($dept->users()->exists()) {
            return response()->json([
                'code' => 422,
                'message' => '该部门下存在用户，无法删除',
            ], 422);
        }

        $dept->delete();

        return response()->json([
            'code' => 200,
            'message' => '删除成功',
        ]);
    }
}
