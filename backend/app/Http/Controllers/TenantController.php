<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use App\Services\DataScopeService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Dept;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TenantController extends Controller
{
    public function current(): JsonResponse
    {
        $user = TenantContext::getUser();
        $tenant = Tenant::find(TenantContext::getTenantId());

        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'code' => 403,
                'message' => '租户已过期或被禁用',
            ], 403);
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'tenant' => $tenant,
                'user' => $user,
                'scope' => DataScopeService::buildFilterParams(),
            ],
        ]);
    }

    public function refreshContext(): JsonResponse
    {
        $user = TenantContext::getUser();
        if (!$user) {
            return response()->json([
                'code' => 401,
                'message' => '未登录',
            ], 401);
        }

        $freshUser = User::with(['role', 'department'])->find($user->id);
        if (!$freshUser) {
            return response()->json([
                'code' => 404,
                'message' => '用户不存在',
            ], 404);
        }

        TenantContext::setUser($freshUser);

        $tenant = Tenant::find($freshUser->tenant_id);

        $cacheKey = "tenant_context:{$freshUser->id}";
        Cache::put($cacheKey, [
            'user' => $freshUser->toArray(),
            'scope' => DataScopeService::buildFilterParams(),
            'tenant' => $tenant ? $tenant->toArray() : null,
            'refreshed_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        return response()->json([
            'code' => 200,
            'message' => '上下文已刷新',
            'data' => [
                'tenant' => $tenant,
                'user' => $freshUser,
                'scope' => DataScopeService::buildFilterParams(),
                'refreshed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function dataScope(): JsonResponse
    {
        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => DataScopeService::buildFilterParams(),
        ]);
    }

    public function switchTenant(Request $request): JsonResponse
    {
        if (!TenantContext::isSuperAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权切换租户',
            ], 403);
        }

        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $tenant = Tenant::find($validated['tenant_id']);
        if (!$tenant->isActive()) {
            return response()->json([
                'code' => 422,
                'message' => '目标租户不可用',
            ], 422);
        }

        TenantContext::setTenantId($tenant->id);

        return response()->json([
            'code' => 200,
            'message' => '租户已切换',
            'data' => [
                'tenant' => $tenant,
                'scope' => DataScopeService::buildFilterParams(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if (!TenantContext::isSuperAdmin()) {
            return response()->json([
                'code' => 403,
                'message' => '无权访问',
            ], 403);
        }

        $query = Tenant::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        $tenants = $query->orderBy('id', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $tenants,
        ]);
    }
}
