<?php

namespace App\Services\Pricing;

use App\Models\ProductPriceRule;

class ProductPriceRuleService
{
    /**
     * Calculate final price
     */
    public function calculate(float $basePrice, string $productId = null): float
    {
        $price = $basePrice;

        $rule = ProductPriceRule::where('active', true)
            ->where(function ($q) use ($productId) {
                $q->whereNull('product_id');

                if ($productId) {
                    $q->orWhere('product_id', $productId);
                }
            })
            ->first();

        // যদি কোনো active rule না থাকে
        if (! $rule) {
            return round($price, 2);
        }

        return max(
            round($this->applyRule($price, $rule), 2),
            0
        );
    }

    /**
     * Apply active rule
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
