<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Postgres\PostgresController;

 Route::prefix('postgres')->group(function () {
     // 🔹 Get all categories from PostgreSQL
     Route::get('/categories', [PostgresController::class, 'GetCategories']);
     Route::get('/shippings', [PostgresController::class, 'GetShipping']);
     Route::get('/turn_around_times', [PostgresController::class, 'GetTurnAroundTime']);
     Route::get('/taxs', [PostgresController::class, 'GetTax']);
     Route::get('/products', [PostgresController::class, 'transferAllProducts']);
 });
