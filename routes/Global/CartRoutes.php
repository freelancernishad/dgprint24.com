<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\AuthenticateAdmin;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Controllers\Global\CartController;

Route::prefix('admin')->group(function () {
    Route::middleware(AuthenticateAdmin::class)->group(function () {

       });
});




Route::middleware([StartSession::class])->group(function () {
      Route::get('get/cart/list', [CartController::class, 'getFormatedCartItems']);
    Route::prefix('cart')->group(function () {





        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
    });
});

  Route::delete('clear/cart/items/{id}', [CartController::class, 'clearCartItems']);
