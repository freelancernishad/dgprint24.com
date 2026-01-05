<?php

namespace App\Services\Pricing;

use App\Models\ProductPriceRule;
use Carbon\Carbon;

class ProductPriceRuleService
{
    /**
     * Calculate final price for a product
     */
    public function calculate(float $basePrice, string $productId): float
    {
        $price = $basePrice;
        $today = Carbon::today();

        $rules = ProductPriceRule::where('active', true)
            ->where(function ($q) use ($productId) {
                $q->whereNull('product_id')
                  ->orWhere('product_id', $productId);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $today);
            })
            ->orderBy('id') // predictable order
            ->get();

        foreach ($rules as $rule) {
            $price = $this->applyRule($price, $rule);
        }

        return max(round($price, 2), 0);
    }

    /**
     * Apply single rule
     */
    protected function applyRule(float $price, ProductPriceRule $rule): float
    {
        if ($rule->type === 'discount') {
            return $rule->value_type === 'percentage'
                ? $price - ($price * $rule->value / 100)
                : $price - $rule->value;
        }

        // price_add
        return $rule->value_type === 'percentage'
            ? $price + ($price * $rule->value / 100)
            : $price + $rule->value;
    }
}
