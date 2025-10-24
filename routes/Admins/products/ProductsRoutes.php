<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateAdmin;

use App\Http\Controllers\Global\Products\ProductController;
use App\Http\Controllers\Global\Products\CategoryController;
use App\Http\Controllers\Admin\Products\AdminProductController;
use App\Http\Controllers\Admin\Products\AdminCategoryController;
use App\Http\Controllers\Admin\Subscriptions\PlanSubscriptionsController;

Route::prefix('admin')->middleware(AuthenticateAdmin::class)->group(function () {

    // অ্যাডমিন ক্যাটাগরি রিলেটেড রাউটসমূহ
    Route::apiResource('categories', AdminCategoryController::class);
    // এই লাইনটি নিচের রাউটগুলো তৈরি করবে:
    // GET    /api/admin/categories (সব ক্যাটাগরির লিস্ট)
    // GET    /api/admin/categories/{category} (একটি নির্দিষ্ট ক্যাটাগরির বিবরণ)
    // POST   /api/admin/categories (নতুন ক্যাটাগরি তৈরি - যদিও আপনি ব্যবহার নাও করতে পারেন)
    // PUT    /api/admin/categories/{category} (ক্যাটাগরি আপডেট)
    // DELETE /api/admin/categories/{category} (ক্যাটাগরি ডিলিট)

    // অ্যাডমিন প্রোডাক্ট রিলেটেড রাউটসমূহ
    Route::apiResource('products', AdminProductController::class);

});


// --- পাবলিক রাউটসমূহ ---
Route::get('/categories', [CategoryController::class, 'index']); // সব অ্যাকটিভ ক্যাটাগরির লিস্ট
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{productId}', [ProductController::class, 'show']);
Route::get('/products/{productId}/price', [ProductController::class, 'getPrice']);
