<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Postgres\PostgresController;

 Route::prefix('postgres')->group(function () {
     // 🔹 Get all categories from PostgreSQL
     Route::get('/categories', [PostgresController::class, 'GetCategories']);
 });
