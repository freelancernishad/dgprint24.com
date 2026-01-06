<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CartCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     * Returns:
     * {
     *   "cartItems": [ ... ],
     *   "cartTotals": { ... }   // optional, derived if not present
     * }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
         $cartItems = CartItemResource::collection($this->collection)->resolve();

        // derive cartTotals if your source doesn't provide a top-level summary
        $cartTotals = [
            'baseSubtotal' => 0,
            'digitalProofs' => 0,
            'jobSample' => 0,
            'totalShipping' => 0,
            'totalTax' => 0,
            'extrasTotal' => 0,     // NEW: sum of extra_selected_options amounts across items
            'discount' => 0,
            'price_add' => 0,
            'grandTotal' => 0,
        ];

        foreach ($cartItems as $ci) {
            $cartTotals['baseSubtotal'] += $ci['pricing']['baseSubtotal'] ?? 0;
            $cartTotals['digitalProofs'] += $ci['pricing']['digitalProofs'] ?? 0;
            $cartTotals['jobSample'] += $ci['pricing']['jobSample'] ?? 0;
            $cartTotals['totalShipping'] += $ci['pricing']['totalShipping'] ?? 0;
            $cartTotals['totalTax'] += $ci['pricing']['totalTax'] ?? 0;
            $cartTotals['extrasTotal'] += $ci['pricing']['extrasTotal'] ?? ($ci['extras']['extrasTotal'] ?? 0);

            $cartTotals['discount'] += $ci['pricing']['discount'] ?? 0;
            $cartTotals['price_add'] += $ci['pricing']['price_add'] ?? 0;

            $cartTotals['grandTotal'] += $ci['pricing']['total'] ?? 0;
        }

        return [
            'session_id' => $cartItems[0]['session_id'] ?? null,
            'cartItems' => $cartItems,
            'cartTotals' => $cartTotals,
        ];
    }
}
