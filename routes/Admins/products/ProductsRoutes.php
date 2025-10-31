<?php

use App\Models\Shipping;
use App\Models\TurnAroundTime;

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\TurnAroundTimeController;
use App\Http\Controllers\Global\Products\ProductController;
use App\Http\Controllers\Global\Products\CategoryController;
use App\Http\Controllers\Admin\Products\AdminProductController;
use App\Http\Controllers\Admin\Products\AdminCategoryController;
use App\Http\Controllers\Admin\Subscriptions\PlanSubscriptionsController;

Route::prefix('admin')->middleware(AuthenticateAdmin::class)->group(function () {

    // অ্যাডমিন ক্যাটাগরি রিলেটেড রাউটসমূহ
    // অ্যাডমিন ক্যাটাগরি রিলেটেড রাউটসমূহ (separate routes)
    Route::get('categories', [AdminCategoryController::class, 'index']);
    Route::get('categories/{category}', [AdminCategoryController::class, 'show']);
    Route::post('categories', [AdminCategoryController::class, 'store']);
    Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
    Route::post('categories/{category}', [AdminCategoryController::class, 'update']);
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);

    // এই লাইনটি নিচের রাউটগুলো তৈরি করবে:
    // GET    /api/admin/categories (সব ক্যাটাগরির লিস্ট)
    // GET    /api/admin/categories/{category} (একটি নির্দিষ্ট ক্যাটাগরির বিবরণ)
    // POST   /api/admin/categories (নতুন ক্যাটাগরি তৈরি - যদিও আপনি ব্যবহার নাও করতে পারেন)
    // PUT    /api/admin/categories/{category} (ক্যাটাগরি আপডেট)
    // DELETE /api/admin/categories/{category} (ক্যাটাগরি ডিলিট)

    // অ্যাডমিন প্রোডাক্ট রিলেটেড রাউটসমূহ
    Route::apiResource('products', AdminProductController::class);





    // টার্নআরাউন্ড টাইম সংক্রান্ত CRUD রাউট
    // টার্নআরাউন্ড টাইম রিলেটেড রাউটসমূহ (separate routes)
    Route::get('turnaround-times', [TurnAroundTimeController::class, 'index']);
    Route::get('turnaround-time/{turnaround_time}', [TurnAroundTimeController::class, 'show']);
    Route::post('turnaround-times', [TurnAroundTimeController::class, 'store']);
    Route::put('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']);
    Route::post('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']);
    Route::patch('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']);
    // Soft delete
    Route::delete('turnaround-time/{turnaround_time_id}', [TurnAroundTimeController::class, 'destroy']);
    // Permanent delete
    Route::delete('turnaround-time/{turnaround_time_id}/force', [TurnAroundTimeController::class, 'forceDestroy']);
    // Restore
    Route::post('turnaround-time/{turnaround_time_id}/restore', [TurnAroundTimeController::class, 'restore']);



        // কাস্টম রাউট মডেল বাইন্ডিং: shipping_id দিয়ে মডেল খুঁজুন
        Route::bind('shipping', function ($value) {
            return Shipping::where('shipping_id', $value)->firstOrFail();
        });

        // শিপিং সংক্রান্ত CRUD রাউট
        Route::apiResource('shippings', ShippingController::class);




        // ট্যাক্স সংক্রান্ত CRUD রাউট
        Route::apiResource('taxes', TaxController::class);




});


// --- পাবলিক রাউটসমূহ ---
Route::get('/categories', [CategoryController::class, 'index']); // সব অ্যাকটিভ ক্যাটাগরির লিস্ট
Route::get('products/category/{category_id}', [ProductController::class, 'getByCategory']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{productId}', [ProductController::class, 'show']);
Route::get('/products/{productId}/price', [ProductController::class, 'getPrice']);
