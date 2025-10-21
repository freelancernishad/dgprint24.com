<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Common\SystemSettings\SystemSettingController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/db-check', function () {
    try {
        DB::connection()->getPdo(); // DB connection check
        $databaseName = DB::connection()->getDatabaseName();
        return "✅ Database connected successfully! Database name: " . $databaseName;
    } catch (\Exception $e) {
        return "❌ Database connection failed: " . $e->getMessage();
    }
});

Route::get('/run-migrate', function() {
    Artisan::call('migrate', ['--force' => true]);
    return "Migrations completed!";
});



// For web routes
Route::get('/clear-cache', [SystemSettingController::class, 'clearCache']);
