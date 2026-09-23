<?php

declare(strict_types=1);

use App\Http\Controllers\Vendor\VendorPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:vendor', 'approved-vendor'])->group(function () {
    Route::get('/vendor-portal', [VendorPortalController::class, 'dashboard'])->name('vendor.dashboard');
    Route::get('/vendor-portal/connections', [VendorPortalController::class, 'dashboard'])->defaults('section', 'connections')->name('vendor.connections');
    Route::get('/vendor-portal/endpoints', [VendorPortalController::class, 'dashboard'])->defaults('section', 'endpoints')->name('vendor.endpoints');
    Route::get('/vendor-portal/contracts', [VendorPortalController::class, 'dashboard'])->defaults('section', 'contracts')->name('vendor.contracts');
    Route::redirect('/vendor-portal/authentication', '/vendor-portal/connections')->name('vendor.authentication');
    Route::get('/vendor-portal/reference', [VendorPortalController::class, 'dashboard'])->defaults('section', 'reference')->name('vendor.reference');
    Route::post('/vendor-portal/connections', [VendorPortalController::class, 'storeConnection'])->name('vendor.connection.store');
    Route::post('/vendor-portal/connections/{connection}', [VendorPortalController::class, 'updateConnection'])->whereNumber('connection')->name('vendor.connection.update');
    Route::post('/vendor-portal/connections/{connection}/test-login', [VendorPortalController::class, 'testConnectionLogin'])->whereNumber('connection')->name('vendor.connection.testLogin');
    Route::post('/vendor-portal/test/{binding}', [VendorPortalController::class, 'runEndpointTest'])->name('vendor.bindings.test');
    Route::post('/vendor-portal/test-all', [VendorPortalController::class, 'runAllEndpointTests'])->name('vendor.bindings.testAll');
    Route::post('/vendor-portal/bindings/{binding}', [VendorPortalController::class, 'updateBindingDraft'])->name('vendor.bindings.updateDraft');
    Route::post('/vendor-portal/bindings/{binding}/submit', [VendorPortalController::class, 'submitBindingDraft'])->name('vendor.bindings.submit');
});
