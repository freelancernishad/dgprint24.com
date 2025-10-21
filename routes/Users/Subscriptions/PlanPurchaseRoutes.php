<?php


use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthenticateUser;
use App\Http\Controllers\User\Plan\UserPlanController;
use App\Http\Controllers\User\Plan\Stripe\PlanSubscriptionController;
use App\Http\Controllers\Common\Gateways\Stripe\StripeWebhookController;


Route::prefix('/user')->group(function () {
    Route::middleware(AuthenticateUser::class)->group(function () { // Applying user middleware

        Route::get('/plan/active', [UserPlanController::class, 'getActivePlan']);
        Route::get('/plan/history', [UserPlanController::class, 'getSubscriptionHistory']);


        Route::post('/plans/purchase', [PlanSubscriptionController::class, 'PurchasePlan']);
        Route::post('/subscriptions/{subscriptionId}/cancel', [PlanSubscriptionController::class, 'cancelSubscription']);

    });
});

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
