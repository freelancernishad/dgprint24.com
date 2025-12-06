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

/*
|--------------------------------------------------------------------------
| Admin Routes (Protected by AuthenticateAdmin Middleware)
|--------------------------------------------------------------------------
| All admin-related routes—Category, Product, Shipping, Tax, Turnaround Time.
| These routes require admin authentication and use the URL prefix "admin".
*/
Route::prefix('admin')->middleware(AuthenticateAdmin::class)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Category Routes
    |--------------------------------------------------------------------------
    | Standalone CRUD routes for managing categories.
    */
    Route::get('categories', [AdminCategoryController::class, 'index']);                 // List categories
    Route::get('categories/{category}', [AdminCategoryController::class, 'show']);       // Show single category
    Route::post('categories', [AdminCategoryController::class, 'store']);                // Create category
    Route::put('categories/{category}', [AdminCategoryController::class, 'update']);     // Update category (PUT)
    Route::post('categories/{category}', [AdminCategoryController::class, 'update']);    // Update fallback using POST
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update']);   // Partial update
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']); // Delete category

    Route::patch('categories/{id}/toggle-navbar', [AdminCategoryController::class, 'toggleShowInNavbar']); // Toggle navbar visibility


    /*
    |--------------------------------------------------------------------------
    | Admin Product Routes
    |--------------------------------------------------------------------------
    | CRUD operations for products.
    */
    Route::get('products', [AdminProductController::class, 'index']);                   // List products
    Route::get('products/{product}', [AdminProductController::class, 'show']);          // Show single product
    Route::post('products', [AdminProductController::class, 'store']);                  // Add new product
    Route::put('products/{product}', [AdminProductController::class, 'update']);        // Update product (PUT)
    Route::post('products/{product}', [AdminProductController::class, 'update']);       // Update fallback using POST
    Route::patch('products/{product}', [AdminProductController::class, 'update']);      // Partial update
    Route::delete('product/{product}', [AdminProductController::class, 'destroy']);     // Delete product

    Route::patch('products/{product}/toggle-popular', [AdminProductController::class, 'togglePopular']); // Toggle popular status
    Route::get('single/product/{id}/for/edit', [AdminProductController::class, 'showForEdit']);          // Fetch product for editing


    /*
    |--------------------------------------------------------------------------
    | Turnaround Time Routes
    |--------------------------------------------------------------------------
    | CRUD + Soft Delete + Restore + Force Delete.
    */
    Route::get('turnaround-times', [TurnAroundTimeController::class, 'index']);                     // List turnaround times
    Route::get('turnaround-time/{turnaround_time}', [TurnAroundTimeController::class, 'show']);     // Show specific item
    Route::post('turnaround-times', [TurnAroundTimeController::class, 'store']);                    // Create
    Route::put('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']);  // Update
    Route::post('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']); // Fallback POST update
    Route::patch('turnaround-times/{turnaround_time}', [TurnAroundTimeController::class, 'update']); // Partial update

    Route::delete('turnaround-time/{turnaround_time_id}', [TurnAroundTimeController::class, 'destroy']);       // Soft delete
    Route::get('turnaround-times/trashed', [TurnAroundTimeController::class, 'trashed']);                      // List trashed
    Route::delete('turnaround-time/{turnaround_time_id}/force', [TurnAroundTimeController::class, 'forceDestroy']); // Permanent delete
    Route::post('turnaround-time/{turnaround_time_id}/restore', [TurnAroundTimeController::class, 'restore']);     // Restore soft deleted


    /*
    |--------------------------------------------------------------------------
    | Shipping Routes
    |--------------------------------------------------------------------------
    | Standalone CRUD routes for managing shipping methods.
    */
    // Custom route model binding (optional):
    // Route::bind('shipping', function ($value) {
    //     return Shipping::where('shipping_id', $value)->firstOrFail();
    // });

    Route::get('shippings', [ShippingController::class, 'index'])->name('shippings.index');        // List shipping options
    Route::post('shippings', [ShippingController::class, 'store'])->name('shippings.store');       // Create shipping
    Route::get('shipping/{shipping}', [ShippingController::class, 'show'])->name('shippings.show'); // Show shipping
    Route::post('shippings/{shipping}', [ShippingController::class, 'update'])->name('shippings.update'); // Update via POST
    Route::put('shippings/{shipping}', [ShippingController::class, 'update'])->name('shippings.update');  // Update via PUT
    Route::patch('shippings/{shipping}', [ShippingController::class, 'update'])->name('shippings.update'); // Partial update
    Route::delete('shipping/{shipping}', [ShippingController::class, 'destroy'])->name('shippings.destroy'); // Delete shipping


    /*
    |--------------------------------------------------------------------------
    | Tax Routes
    |--------------------------------------------------------------------------
    | Using apiResource for full REST functionality.
    */
    Route::apiResource('taxes', TaxController::class);

}); // End Admin Group


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| Frontend category and product endpoints.
*/
Route::get('/categories', [CategoryController::class, 'index']);                                // All active categories
Route::get('/categories/navbar', [CategoryController::class, 'getNavbarCategories']);           // Navbar categories
Route::get('/categories/navbar/with/products', [CategoryController::class, 'getNavbarCategoriesWithProducts']); // Navbar + products

// Product routes
Route::get('products/category/{category_id}', [ProductController::class, 'getByCategory']); // Products by category
Route::get('/products', [ProductController::class, 'index']);                               // All products
Route::get('/products/popular', [ProductController::class, 'mostPopular']);                 // Popular products
Route::get('/product/{productId}', [ProductController::class, 'show']);                     // Single product detail

// Price routes
Route::get('/product/{productId}/price', [ProductController::class, 'getPrice']);           // Get price
Route::post('/product/{productId}/price', [ProductController::class, 'getPrice']);          // Price via POST

// tax price route
Route::get('/taxes/price', [TaxController::class, 'getTaxByLocation']);
Route::post('/taxes/price', [TaxController::class, 'getTaxByLocation']);
