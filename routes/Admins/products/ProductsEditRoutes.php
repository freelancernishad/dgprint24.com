<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Controllers\Admin\ProductEditController;
use App\Http\Controllers\Admin\Products\ProductEdit\PriceRangeController;
use App\Http\Controllers\Admin\Products\ProductEdit\ProductFaqController;
use App\Http\Controllers\Admin\Products\ProductEdit\PriceConfigController;
use App\Http\Controllers\Admin\Products\ProductEdit\ProductBasicController;
use App\Http\Controllers\Admin\Products\ProductEdit\ProductImageController;
use App\Http\Controllers\Admin\Products\ProductEdit\ShippingRangeController;
use App\Http\Controllers\Admin\Products\ProductEdit\ProductOptionsController;
use App\Http\Controllers\Admin\Products\ProductEdit\TurnaroundRangeController;
use App\Http\Controllers\Admin\Products\ProductEdit\DimensionPricingController;
use App\Http\Controllers\Admin\Products\ProductEdit\PriceConfigChildController;

Route::prefix('admin')->middleware(AuthenticateAdmin::class)->group(function () {


    Route::patch('products/{product}/basic', [ProductBasicController::class, 'updateBasic']);


    /**
     |--------------------------------------------------------------------------
     | PRODUCT IMAGES
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/images', [ProductImageController::class, 'getImages']);
    Route::post('products/{product}/images', [ProductImageController::class, 'addImage']);
    Route::put('products/{product}/images/sync', [ProductImageController::class, 'syncImages']);
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'deleteImage']);



    /**
     |--------------------------------------------------------------------------
     | PRODUCT FAQS
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/faqs', [ProductFaqController::class, 'getFaqs']);
    Route::post('products/{product}/faqs', [ProductFaqController::class, 'addFaq']);
    Route::patch('products/{product}/faqs/{faq}', [ProductFaqController::class, 'updateFaq']);
    Route::put('products/{product}/faqs/sync', [ProductFaqController::class, 'syncFaqs']);
    Route::delete('products/{product}/faqs/{faq}', [ProductFaqController::class, 'deleteFaq']);



    // GET product dynamic options
    Route::get('products/{product}/options', [ProductOptionsController::class, 'getDynamicOptions']);

    // PATCH update product dynamic options (mode: merge|replace)
    Route::patch('products/{product}/options', [ProductOptionsController::class, 'updateDynamicOptions']);




    /**
     |--------------------------------------------------------------------------
     | PRODUCT PRICE CONFIGURATIONS
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/price-configs', [PriceConfigController::class, 'getPriceConfigs']);
    Route::post('products/{product}/price-configs', [PriceConfigController::class, 'addPriceConfig']);
    Route::put('products/{product}/price-configs/sync', [PriceConfigController::class, 'syncPriceConfigs']);
    Route::delete('products/{product}/price-configs/{config}', [PriceConfigController::class, 'deletePriceConfig']);

    Route::patch('products/{product}/price-configs/{config}',[PriceConfigController::class, 'updatePriceConfig']);
    Route::put('products/{product}/price-configs/{config}',[PriceConfigController::class, 'updatePriceConfig']);
    Route::post('products/{product}/price-configs/{config}',[PriceConfigController::class, 'updatePriceConfig']);



    /**
     |--------------------------------------------------------------------------
     | PRICE CONFIG → SHIPPINGS / TURNAROUNDS (nested)
     |--------------------------------------------------------------------------
     */
    Route::post('price-configs/{config}/shippings', [PriceConfigChildController::class, 'addShipping']);
    Route::delete('price-configs/{config}/shippings/{id}', [PriceConfigChildController::class, 'deleteShipping']);

    Route::post('price-configs/{config}/turnarounds', [PriceConfigChildController::class, 'addTurnaround']);
    Route::delete('price-configs/{config}/turnarounds/{id}', [PriceConfigChildController::class, 'deleteTurnaround']);


    /**
     |--------------------------------------------------------------------------
     | PRODUCT PRICE RANGES
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/price-ranges', [PriceRangeController::class, 'getPriceRanges']);
    Route::post('products/{product}/price-ranges', [PriceRangeController::class, 'addPriceRange']);
    Route::put('products/{product}/price-ranges/sync', [PriceRangeController::class, 'syncPriceRanges']);
    Route::delete('products/{product}/price-ranges/{range}', [PriceRangeController::class, 'deletePriceRange']);



    /**
     |--------------------------------------------------------------------------
     | PRODUCT SHIPPING RANGES
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/shipping-ranges', [ShippingRangeController::class, 'getShippingRanges']);
    Route::post('products/{product}/shipping-ranges', [ShippingRangeController::class, 'addShippingRange']);
    Route::put('products/{product}/shipping-ranges/sync', [ShippingRangeController::class, 'syncShippingRanges']);
    Route::delete('products/{product}/shipping-ranges/{range}', [ShippingRangeController::class, 'deleteShippingRange']);



    /**
     |--------------------------------------------------------------------------
     | PRODUCT TURNAROUND RANGES
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/turnaround-ranges', [TurnaroundRangeController::class, 'getTurnaroundRanges']);
    Route::post('products/{product}/turnaround-ranges', [TurnaroundRangeController::class, 'addTurnaroundRange']);
    Route::put('products/{product}/turnaround-ranges/sync', [TurnaroundRangeController::class, 'syncTurnaroundRanges']);
    Route::delete('products/{product}/turnaround-ranges/{range}', [TurnaroundRangeController::class, 'deleteTurnaroundRange']);



    /**
     |--------------------------------------------------------------------------
     | DIMENSION PRICING (one-to-one)
     |--------------------------------------------------------------------------
     */
    Route::get('products/{product}/dimension-pricing', [DimensionPricingController::class, 'getDimensionPricing']);
    Route::patch('products/{product}/dimension-pricing', [DimensionPricingController::class, 'patchDimensionPricing']);
});
