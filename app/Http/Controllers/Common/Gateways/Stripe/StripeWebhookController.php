<?php

namespace App\Http\Controllers\Common\Gateways\Stripe;

use Stripe\Stripe;
use Stripe\Webhook;
use App\Models\Plan;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\PlanSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\StripeWebhookRouter;
use App\Http\Controllers\Controller;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
{
    $payload = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $secret = config('STRIPE_WEBHOOK_SECRET');

    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Exception $e) {
        return response('Invalid', 400);
    }

    StripeWebhookRouter::dispatch($event->type, $event->data->object);

    return response('ok', 200);
}
}
