<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TenantMiddleware;

Route::middleware(['auth:api', TenantMiddleware::class])->group(function () {

    Route::prefix('tenant')->group(function () {
        Route::get('/current', [App\Http\Controllers\TenantController::class, 'current']);
        Route::post('/refresh-context', [App\Http\Controllers\TenantController::class, 'refreshContext']);
        Route::get('/data-scope', [App\Http\Controllers\TenantController::class, 'dataScope']);
        Route::post('/switch', [App\Http\Controllers\TenantController::class, 'switchTenant']);
        Route::get('/', [App\Http\Controllers\TenantController::class, 'index']);
    });

    Route::apiResource('courses', App\Http\Controllers\CourseController::class);
    Route::get('courses-all', [App\Http\Controllers\CourseController::class, 'all']);
    Route::get('courses-data-scope-audit', [App\Http\Controllers\CourseController::class, 'dataScopeAudit']);
    Route::get('courses-export-data-scope-audit', [App\Http\Controllers\CourseController::class, 'exportDataScopeAudit']);

    Route::apiResource('depts', App\Http\Controllers\DeptController::class);
    Route::get('depts-tree', [App\Http\Controllers\DeptController::class, 'tree']);

    Route::apiResource('roles', App\Http\Controllers\RoleController::class);
});
