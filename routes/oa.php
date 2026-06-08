<?php

use App\Http\Controllers\OA\AuthController;
use App\Http\Controllers\OA\EmployeesController;
use App\Http\Controllers\OA\MenusController;
use App\Http\Controllers\OA\PermissionsController;
use App\Http\Controllers\OA\RolesController;
use Illuminate\Support\Facades\Route;

// 授权
Route::prefix('auth')->group(function (): void {
    Route::post('signin', [AuthController::class, 'signIn']);
    Route::post('signout', [AuthController::class, 'signOut']);
});

// 组织架构
Route::prefix('org')->group(function (): void {
    // 雇员
    Route::apiResource('employees', EmployeesController::class)->except(['show'])->where(['employee' => '[0-9]+']);
    Route::patch('employees/{employee}/reset-password', [EmployeesController::class, 'resetPassword'])->where(['employee' => '[0-9]+']);
    Route::patch('employees/{employee}/edit-status', [EmployeesController::class, 'editStatus'])->where(['employee' => '[0-9]+']);
    Route::get('employees/{employee}/roles', [EmployeesController::class, 'show'])->where(['employee' => '[0-9]+']);
    Route::get('employees/roles/for-autocomplete', [EmployeesController::class, 'rolesForAutocomplete']);
    Route::patch('employees/{employee}/roles', [EmployeesController::class, 'assignRoles'])->where(['employee' => '[0-9]+']);

    // 权限
    Route::apiResource('permissions', PermissionsController::class)->except(['show', 'update']);
    Route::get('permission/form-params', [PermissionsController::class, 'formParams']);

    // 角色
    Route::apiResource('roles', RolesController::class);
    Route::patch('roles/{role}/change-status', [RolesController::class, 'changeStatus'])->where(['role' => '[0-9]+']);
    Route::get('roles/permissions/assignable', [RolesController::class, 'assignablePermissions']);

    // 菜单
    Route::apiResource('menus', MenusController::class)->except(['show']);
    Route::get('menu/form-params', [MenusController::class, 'formParams']);
});
