<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Controllers\Admin\Products\ProductPriceRuleController;


/*
|--------------------------------------------------------------------------
| Admin Routes (Protected by AuthenticateAdmin Middleware)
|--------------------------------------------------------------------------
| All admin-related routes—Category, Product, Shipping, Tax, Turnaround Time.
| These routes require admin authentication and use the URL prefix "admin".
*/
Route::prefix('admin')->middleware(AuthenticateAdmin::class)->group(function () {

     // list all rules
    Route::get(
        'product-price-rules',
        [ProductPriceRuleController::class, 'index']
    );

    // create rule
    Route::post(
        'product-price-rules',
        [ProductPriceRuleController::class, 'store']
    );

    // show single rule
    Route::get(
        'product-price-rules/{productPriceRule}',
        [ProductPriceRuleController::class, 'show']
    );

    // update rule
    Route::put(
        'product-price-rules/{productPriceRule}',
        [ProductPriceRuleController::class, 'update']
    );

    // delete rule
    Route::delete(
        'product-price-rules/{productPriceRule}',
        [ProductPriceRuleController::class, 'destroy']
    );

      Route::patch(
            'product-price-rules/{id}/toggle-activate',
            [ProductPriceRuleController::class, 'activate']
        );


    Route::post(
        'product-price-rules/calculate',
        [ProductPriceRuleController::class, 'calculate']
    );


}); // End Admin Group


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
| Frontend category and product endpoints.
*/


    Route::post('pricing/calculate',[ProductPriceRuleController::class, 'calculate']);
    Route::get('pricing/calculate',[ProductPriceRuleController::class, 'calculate']);
