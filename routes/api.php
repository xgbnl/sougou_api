<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MarketingLeadsController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    // 用户管理
    Route::apiResource('users', UsersController::class)->only(['index', 'store']);
    // 线索列表
    Route::get('marketing-leads',[MarketingLeadsController::class, 'index']);
});
