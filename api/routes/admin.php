<?php

use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\GatewayController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get   ('users',              [UserController::class, 'index']);
    Route::get   ('users/{user}',       [UserController::class, 'show']);
    Route::post  ('users/{user}/suspend',  [UserController::class, 'suspend']);
    Route::post  ('users/{user}/activate', [UserController::class, 'activate']);
    Route::post  ('users/{user}/impersonate', [ImpersonationController::class, 'start']);
    Route::post  ('impersonation/stop',       [ImpersonationController::class, 'stop']);

    Route::apiResource('gateways', GatewayController::class);
    Route::post  ('gateways/{gateway}/reboot',   [GatewayController::class, 'reboot']);
    Route::post  ('gateways/{gateway}/reassign', [GatewayController::class, 'reassign']);
    Route::get   ('gateways/{gateway}/health',   [GatewayController::class, 'health']);

    Route::get('financial/dashboard', [FinancialController::class, 'dashboard']);
    Route::get('financial/mrr',       [FinancialController::class, 'mrr']);
    Route::get('financial/churn',     [FinancialController::class, 'churn']);

    Route::get('system/queue',   [SystemController::class, 'queue']);
    Route::get('system/devices', [SystemController::class, 'devices']);
    Route::get('system/audit',   [SystemController::class, 'audit']);
});
