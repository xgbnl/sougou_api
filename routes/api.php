<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaiduDeliveryController;
use App\Http\Controllers\MarketingLeadsController;
use App\Http\Controllers\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('auth/login', [AuthController::class, 'login']);

Route::post('baidu/delivery',[BaiduDeliveryController::class,'handle']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    // Dashboard
    Route::get('dashboard/marketing-leads/stats', [MarketingLeadsController::class, 'stats']);
    // 用户管理
    Route::apiResource('users', UsersController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('users/{id}/accounts', [UsersController::class, 'accounts'])->where(['id' => '[0-9]+']);
    Route::patch('users/{id}/accounts', [UsersController::class, 'syncAccounts'])->where(['id' => '[0-9]+']);
    // 账户管理
    Route::apiResource('accounts', AccountsController::class)->only(['index', 'store', 'update']);
    // 线索列表
    Route::get('marketing-leads', [MarketingLeadsController::class, 'index']);
    Route::post('marketing-leads/import', [MarketingLeadsController::class, 'import']);
    Route::get('marketing-leads/export', [MarketingLeadsController::class, 'export'])
        ->withoutMiddleware('body.advice');
});
