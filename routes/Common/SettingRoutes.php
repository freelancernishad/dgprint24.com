<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingController;
use App\Http\Middleware\AuthenticateAdmin;


Route::prefix('admin')->group(function () {
    Route::middleware(AuthenticateAdmin::class)->group(function () {
        Route::post('/settings', [SettingController::class, 'storeOrUpdate']);
        Route::delete('/settings/{key}', [SettingController::class, 'destroy']);
    });
});

Route::get('/settings', [SettingController::class, 'index']);
Route::get('/settings/{key}', [SettingController::class, 'show']);
