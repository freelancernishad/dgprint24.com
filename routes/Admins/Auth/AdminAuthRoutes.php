<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Controllers\Auth\Admin\AdminAuthController;
use App\Http\Controllers\Auth\Admin\AdminVerificationController;
use App\Http\Controllers\Auth\Admin\AdminPasswordResetController;
use App\Http\Controllers\Admin\AdminManagement\AdminManagementController;

Route::prefix('auth/admin')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login');
    Route::post('register', [AdminAuthController::class, 'register']);

    Route::middleware(AuthenticateAdmin::class)->group(function () { // Applying admin middleware
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::post('/me', [AdminManagementController::class, 'ProfileUpdate']);
        Route::post('/change-password', [AdminAuthController::class, 'changePassword']);
        Route::get('check-token', [AdminAuthController::class, 'checkToken']);
    });
});

// Password reset routes
Route::post('admin/password/email', [AdminPasswordResetController::class, 'sendResetLinkEmail']);
Route::post('admin/password/reset', [AdminPasswordResetController::class, 'reset']);

Route::post('admin/verify-otp', [AdminVerificationController::class, 'verifyOtp']);
Route::post('admin/resend/otp', [AdminVerificationController::class, 'resendOtp']);
Route::get('admin/email/verify/{hash}', [AdminVerificationController::class, 'verifyEmail']);
Route::post('admin/resend/verification-link', [AdminVerificationController::class, 'resendVerificationLink']);





// API Version 1
Route::prefix('admin')->group(function () {

    // Admin Management Routes (Protected)
    Route::prefix('user')->name('api.admin-management.')->group(function () {
        Route::get('/', [AdminManagementController::class, 'index'])->name('index');
        Route::post('/', [AdminManagementController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminManagementController::class, 'show'])->name('show');
        Route::post('/{id}', [AdminManagementController::class, 'update'])->name('update');
        Route::delete('/{id}', [AdminManagementController::class, 'destroy'])->name('destroy');
        Route::patch('/{id}/status', [AdminManagementController::class, 'updateStatus'])->name('update-status');
        Route::post('/bulk-update-status', [AdminManagementController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
    });

});
