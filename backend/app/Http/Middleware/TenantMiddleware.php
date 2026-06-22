<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $tenantId = $this->resolveTenantId($request);

            if (empty($tenantId)) {
                return response()->json([
                    'code' => 403,
                    'message' => '无法识别租户信息，请重新登录'
                ], 403);
            }

            TenantContext::setTenantId($tenantId);

            if (Auth::check()) {
                $user = Auth::user();

                if (!TenantContext::isSuperAdmin() && $user->tenant_id != $tenantId) {
                    return response()->json([
                        'code' => 403,
                        'message' => '租户信息不匹配，访问被拒绝'
                    ], 403);
                }

                TenantContext::setUser($user);
            }

            $request->attributes->set('tenant_id', $tenantId);

            return $next($request);
        } catch (\Exception $e) {
            TenantContext::reset();
            throw $e;
        }
    }

    protected function resolveTenantId(Request $request)
    {
        $headerTenantId = $request->header('X-Tenant-Id');
        if (!empty($headerTenantId)) {
            return $headerTenantId;
        }

        $queryTenantId = $request->query('tenant_id');
        if (!empty($queryTenantId)) {
            return $queryTenantId;
        }

        $host = $request->getHost();
        $tenantId = $this->resolveTenantByDomain($host);
        if (!empty($tenantId)) {
            return $tenantId;
        }

        if (Auth::check()) {
            return Auth::user()->tenant_id;
        }

        $tokenTenantId = $this->resolveTenantFromToken($request);
        if (!empty($tokenTenantId)) {
            return $tokenTenantId;
        }

        return null;
    }

    protected function resolveTenantByDomain(string $host): ?string
    {
        $tenantMap = config('tenant.domain_map', []);
        return $tenantMap[$host] ?? null;
    }

    protected function resolveTenantFromToken(Request $request): ?string
    {
        $token = $request->bearerToken();
        if (empty($token)) {
            return null;
        }

        try {
            if (function_exists('jwt_decode')) {
                $payload = jwt_decode($token);
                return $payload->tenant_id ?? null;
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function terminate($request, $response): void
    {
        TenantContext::reset();
    }
}
