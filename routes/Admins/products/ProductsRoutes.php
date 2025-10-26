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
    Route::apiResource('categories', AdminCategoryController::class);
    // এই লাইনটি নিচের রাউটগুলো তৈরি করবে:
    // GET    /api/admin/categories (সব ক্যাটাগরির লিস্ট)
    // GET    /api/admin/categories/{category} (একটি নির্দিষ্ট ক্যাটাগরির বিবরণ)
    // POST   /api/admin/categories (নতুন ক্যাটাগরি তৈরি - যদিও আপনি ব্যবহার নাও করতে পারেন)
    // PUT    /api/admin/categories/{category} (ক্যাটাগরি আপডেট)
    // DELETE /api/admin/categories/{category} (ক্যাটাগরি ডিলিট)

    // অ্যাডমিন প্রোডাক্ট রিলেটেড রাউটসমূহ
    Route::apiResource('products', AdminProductController::class);



        // কাস্টম রাউট মডেল বাইন্ডিং: turnaround_id দিয়ে মডেল খুঁজুন
        Route::bind('turnaround_time', function ($value) {
            return TurnAroundTime::where('turnaround_id', $value)->firstOrFail();
        });

    // টার্নআরাউন্ড টাইম সংক্রান্ত CRUD রাউট
    Route::apiResource('turnaround-times', TurnAroundTimeController::class);



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
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{productId}', [ProductController::class, 'show']);
Route::get('/products/{productId}/price', [ProductController::class, 'getPrice']);
