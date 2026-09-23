<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\JsonTemplateController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminReviewController::class, 'index'])->name('dashboard');
    Route::get('/vendors', [AdminReviewController::class, 'index'])->defaults('section', 'vendors')->name('vendors.index');
    Route::get('/endpoint-reviews', [AdminReviewController::class, 'index'])->defaults('section', 'reviews')->name('endpointReviews.index');
    Route::get('/active-endpoints', [AdminReviewController::class, 'index'])->defaults('section', 'active-endpoints')->name('activeEndpoints.index');
    Route::post('/vendors/{vendor}/approve', [AdminReviewController::class, 'approveVendor'])->name('vendors.approve');
    Route::post('/submissions/{submission}/approve', [AdminReviewController::class, 'approveSubmission'])->name('submissions.approve');

    Route::resource('users', UserManagementController::class)->except(['show', 'destroy']);

    // CRUD JSON Templates (Admin mengelola format data yang dikirim vendor)
    Route::get('/templates', [JsonTemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/guide', [JsonTemplateController::class, 'guide'])->name('templates.guide');
    Route::get('/templates/create', [JsonTemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [JsonTemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{id}/edit', [JsonTemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{id}', [JsonTemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{id}', [JsonTemplateController::class, 'destroy'])->name('templates.destroy');

    // Master data / reference data — kode yang dipakai vendor (ut_code, program_code,
    // catalog_key, process_status_code). Paramita pemilik kode ini, vendor hanya memakai.
    Route::prefix('reference/{type}')->name('reference.')->group(function () {
        Route::get('/', [ReferenceDataController::class, 'index'])->name('index');
        Route::post('/', [ReferenceDataController::class, 'store'])->name('store');
        Route::put('/{id}', [ReferenceDataController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReferenceDataController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle', [ReferenceDataController::class, 'toggle'])->name('toggle');
    });
});
