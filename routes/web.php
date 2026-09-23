<?php

use App\Http\Controllers\Api\MonitoringDataController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();
    if ($user && $user->role === 'vendor') {
        return redirect('/vendor-portal');
    }

    if ($user && $user->role === 'kepala_ut_daerah') {
        return redirect('/do-per-ut-daerah');
    }

    return redirect('/dashboard');
});

Route::middleware(['web'])->group(function () {
    require __DIR__.'/auth.php';
    require __DIR__.'/admin.php';
    require __DIR__.'/vendor.php';
});

// National/program monitoring pages. Regional heads intentionally cannot open these pages.
Route::middleware(['web', 'auth', 'role:admin,kepala_ut_pusat,tutor'])->group(function () {
    Route::view('/dashboard', 'pages.dashboard');
    Route::view('/monitoring-delivery', 'pages.monitoring-delivery');
    Route::view('/do-per-prodi', 'pages.do-per-prodi');
    Route::view('/distribution-map', 'pages.distribution-map');
});

// Regional operational pages. ScopeResolver still enforces the assigned UT on every API call.
Route::middleware(['web', 'auth', 'role:admin,kepala_ut_pusat,kepala_ut_daerah,tutor'])->group(function () {
    Route::view('/do-per-ut-daerah', 'pages.do-per-ut-daerah');
    Route::view('/analisis-sla', 'pages.analisis-sla');
    Route::view('/monitoring-retry', 'pages.monitoring-retry');
});

// Canonical Browser API routes (PRD §11)
// Authenticated via web session; vendor users must use /vendor-portal only.
Route::middleware(['web', 'auth', 'role:admin,kepala_ut_pusat,tutor'])->prefix('api/v1')->group(function () {
    Route::get('/stock/summary', [MonitoringDataController::class, 'stockSummary']);
    Route::get('/stock/items', [MonitoringDataController::class, 'stockItems']);
    Route::get('/stock/matrix', [MonitoringDataController::class, 'stockMatrix']);
});

Route::middleware(['web', 'auth', 'role:admin,kepala_ut_pusat,kepala_ut_daerah,tutor'])->prefix('api/v1')->group(function () {
    Route::get('/orders', [MonitoringDataController::class, 'ordersList']);
    Route::get('/orders/summary', [MonitoringDataController::class, 'ordersSummary']);
    Route::get('/orders/analytics', [MonitoringDataController::class, 'ordersAnalytics']);
    Route::get('/vendors/{vendor}/orders/{source_id}', [MonitoringDataController::class, 'orderDetail']);
    Route::get('/vendors/{vendor}/orders/{source_id}/events', [MonitoringDataController::class, 'orderEvents']);
    Route::get('/vendors/{vendor}/orders/{source_id}/proof', [MonitoringDataController::class, 'orderProof']);

    Route::get('/options/{type}', [MonitoringDataController::class, 'options']);
});
