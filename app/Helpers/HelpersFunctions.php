<?php

namespace App\Helpers;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PriceConfiguration;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class HelpersFunctions
{
    /**
     * JWT token decode
     *
     * @param string|null $field
     * @return array|string|null
     */
    public static function jwtDecode($field = null): array|string|null
    {
        $payload = JWTAuth::parseToken()->getPayload()->toArray();

        if ($field) {
            $value = $payload[$field] ?? null;

            // যদি model field হয়, তবে শুধু class এর নাম return কর
            // if ($field === 'model' && $value) {
            //     return class_basename($value); // App\Models\HotelManagement\Hotel → Hotel
            // }

            return $value;
        }

        return $payload;
    }


    function flattenSelectedOptions(array $options, string $prefix = ''): array {
        $flat = [];

        foreach ($options as $key => $value) {

            // skip "selected" key from key generation
            if ($key === 'selected') {
                continue;
            }

            $newKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                // যদি selected থাকে তাহলে value নাও
                if (isset($value['selected'])) {
                    $flat[$newKey] = $value['selected'];
                }

                // nested হলে recursive
                $flat = array_merge($flat, $this->flattenSelectedOptions($value, $newKey));
            } else {
                $flat[$newKey] = $value;
            }
        }

        return $flat;
    }




    /**
     * Get pricing data based on product ID and parameters.
     *
     * @param array $params
     * @param int $productId
     * @return array
     */
    public static function getPricingData(array $params, $productId)
    {
        // ১. প্রথমে প্রোডাক্টটা খুঁজে নিন
        $product = Product::where('product_id', $productId)->where('active', true)->firstOrFail();

        // প্যারামিটার থেকে ভ্যালুগুলো নিন
        $product_type = $product->product_type ?? 'banner';
        $quantity = $params['runsize'];
        $hasOptions = !empty($params['options']);

        // ভেরিয়েবল মূল্য রাখার জন্য ভেরিয়েবল
        $configurationPrice = 0;
        $configuration_price_plus_quantity_price = 0;
        $finalPrice = 0;
        $priceConfigData = null; // সম্পূর্ণ কনফিগারেশন ডেটা রাখার জন্য

        $price_per_sq_ft = 0;
        $price_for_sq_ft = 0;
        $total_sq_ft = 0;
        $quantity_into_total_sq_ft_price = 0;
        $priceConfigList = [];

        // --- ধাপ ১: কনফিগারেশন মূল্য খুঁজে বের করুন ---
        // যদি ইউজার কোনো অপশন সিলেক্ট করে থাকে
        if ($hasOptions) {


        $query = PriceConfiguration::with(['shippings', 'turnarounds', 'optionsRel'])
            ->where('product_id', $product->id);

        $priceConfigList = $query->get();

        $filteredConfigList = collect();

        foreach ($priceConfigList as $config) {

            $totalOptions = $config->optionsRel->count();
            $matchedOptions = $config->optionsRel->filter(function($opt) use ($params) {
                return isset($params['options'][$opt->key]) && $params['options'][$opt->key] == $opt->value;
            })->count();

            Log::info("PriceConfiguration ID: {$config->id}");
            Log::info("Total Options: {$totalOptions}");
            Log::info("Matched Options: {$matchedOptions}");

            if ($matchedOptions === $totalOptions) {
                Log::info("✅ All options match for this configuration.");
                $filteredConfigList->push($config);
            } else {
                Log::info("❌ Not all options match.");
            }
        }

        $priceConfigList = $filteredConfigList;

        // প্রতিটি প্রাইস কনফিগারেশনের জন্য ডিসকাউন্ট ক্যালকুলেশন করুন
        $priceConfigList = $priceConfigList->map(function($config) use ($quantity) {

                $originalPrice = (float)$config->price;
                $discountAmount = (float)$config->discount;
                $discountPercentage = $originalPrice > 0 ? ($discountAmount / $originalPrice) * 100 : 0;

                $priceAfterDiscount = $originalPrice - $discountAmount;
                $totalPrice = $priceAfterDiscount * $quantity;

                // নতুন প্রপার্টি যোগ করুন
                $config->original_price = $originalPrice;
                $config->discount_amount = $discountAmount;
                $config->discount_percentage = round($discountPercentage, 2);
                $config->price_after_discount = $priceAfterDiscount;

                return $config;
            });


            if($product_type === 'general') {
                $query->where('runsize', $quantity);

            }
            $priceConfig = $filteredConfigList->first();


            // jodi kono price configuration paoa na jay tobe akta veiable a meesage rakhte hobe response a breakdown a thakbe
            $PriceConfigMessage = 'No price configuration found for the selected options.';
            $configurationId = null;
            if ($priceConfig) {
                // ডাটাবেসে যে মূল্য সংরক্ষিত আছে সেটাই নিন
                $configurationPrice = $priceConfig->price;
                $configurationId = $priceConfig->id;
                // ফ্রন্টএন্ডকে শিপিং এবং টার্নআরাউন্ড অপশন দেখানোর জন্য সম্পূর্ণ ডেটা রাখুন
                $priceConfigData = $priceConfig;
                $PriceConfigMessage = "Price configuration found.";
            }
        }

        // --- ধাপ ২: পরিমাণ-ভিত্তিক রেঞ্জ মূল্য খুঁজে বের করুন ---
        $priceRange = $product->priceRanges()
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity)
                    ->orWhere('max_quantity', 0); // 0 মানে unlimited
            })
            ->first();

        if ($priceRange) {
            if (isset($params['sq_ft'])) {
                $total_sq_ft = $params['sq_ft'];
                $price_for_sq_ft = $total_sq_ft * $priceRange->price_per_sq_ft;
                $price_per_sq_ft = $priceRange->price_per_sq_ft;
            }

            // পরিমাণ এবং প্রতি বর্গফুটের দাম গুণ করুন
            $quantity_into_total_sq_ft_price = $quantity * $price_for_sq_ft;
        }

        // --- ধাপ ৩: চূড়ন্ত মূল্য ক্যালকুলেট করুন ---
        $configuration_price_into_quantity_price = $configurationPrice;
        if($product_type === 'banner') {
            $configuration_price_into_quantity_price = $configurationPrice * $quantity;
        }




        $finalPrice = $configuration_price_into_quantity_price + ($quantity_into_total_sq_ft_price ?? 0);

        // --- ধাপ ৪: যদি কোনো ধরনের প্রাইস তথ্য না থাকে, তাহলে এরর দিন ---
        // if ($configurationPrice == 0 && ($quantity_into_total_sq_ft_price ?? 0) == 0) {
        //     return [
        //         'error' => 'Pricing information is not configured for this product or the selected options.'
        //     ];
        // }



// ✅ Detailed Human-Readable Calculation Breakdown
$breakdownMessage = "------ PRICE BREAKDOWN ------\n";
$breakdownMessage .= "🧾 Product: {$product->product_name}\n";
$breakdownMessage .= "📦 Product Type: {$product_type}\n";
$breakdownMessage .= "🔢 Quantity Ordered: " . number_format($quantity) . "\n";
$breakdownMessage .= "💰 Base Price: " . number_format($configurationPrice, 2) . "\n";

// Configuration Price × Quantity
if ($product_type === 'banner') {
    $breakdownMessage .= "📦 Base Price × Quantity = " . number_format($configuration_price_into_quantity_price, 2) . "\n";
} else {
    $breakdownMessage .= "📦 Fixed Base Price (No Quantity Multiplier) = " . number_format($configuration_price_into_quantity_price, 2) . "\n";
}

// Discounts / special config (if available)
if(!empty($priceConfigData)) {
    if(isset($priceConfigData->discount) && $priceConfigData->discount > 0){
        $breakdownMessage .= "💸 Discount Applied: " . number_format($priceConfigData->discount,2) . "\n";
        $breakdownMessage .= "💰 Price After Discount: " . number_format($priceConfigData->price_after_discount,2) . "\n";
    }
}

// Sq Ft Calculation
if (!empty($params['sq_ft']) && $total_sq_ft > 0) {
    $breakdownMessage .= "\n📏 Sq. Ft Pricing Details:\n";
    $breakdownMessage .= "    Total Sq Ft: " . number_format($total_sq_ft, 2) . "\n";
    $breakdownMessage .= "    Rate per Sq Ft: " . number_format($price_per_sq_ft, 2) . "\n";
    $breakdownMessage .= "    Total Sq Ft Price: " . number_format($price_for_sq_ft, 2) . "\n";
    $breakdownMessage .= "    Quantity × Sq Ft Price: " . number_format($quantity_into_total_sq_ft_price, 2) . "\n";
} else {
    $breakdownMessage .= "\n📏 Sq. Ft pricing not applied.\n";
}

// Final Price Summary
$breakdownMessage .= "\n------------------------------\n";
$breakdownMessage .= "✅ FINAL PRICE TO PAY: " . number_format($finalPrice, 2) . "\n";
$breakdownMessage .= "------------------------------\n";

// Optional: Estimated Shipping & Turnaround (if data available)
if(isset($filteredShippingRanges) && $filteredShippingRanges->count() > 0){
    $breakdownMessage .= "\n🚚 Applicable Shipping Options:\n";
    foreach($filteredShippingRanges as $range){
        $breakdownMessage .= "    - " . $range->title . ": " . number_format($range->price,2) . "\n";
    }
}
if(isset($filteredTurnaroundRanges) && $filteredTurnaroundRanges->count() > 0){
    $breakdownMessage .= "\n⏱ Applicable Turnaround Times:\n";
    foreach($filteredTurnaroundRanges as $range){
        $breakdownMessage .= "    - " . $range->title . ": " . $range->days . " days\n";
    }
}



$breakdown = [
    'PriceConfigMessage' => $PriceConfigMessage,
    'configuration_id' => $configurationId,
    'product' => [
        'name' => $product->product_name,
        'type' => $product_type,
    ],
    'quantity' => $quantity,
    'base_price' => round($configurationPrice, 2),
    'configuration_price_times_quantity' => round($configuration_price_into_quantity_price, 2),
    'discount' => isset($priceConfigData->discount) ? round($priceConfigData->discount, 2) : 0,
    'price_after_discount' => isset($priceConfigData->price_after_discount) ? round($priceConfigData->price_after_discount, 2) : null,
    'sq_ft' => [
        'total_sq_ft' => $total_sq_ft,
        'rate_per_sq_ft' => round($price_per_sq_ft, 2),
        'total_sq_ft_price' => round($price_for_sq_ft, 2),
        'quantity_times_sq_ft_price' => round($quantity_into_total_sq_ft_price ?? 0, 2),
    ],
    'final_price' => round($finalPrice, 2),
    'shipping_options' => [],
    'turnaround_times' => []
];

// Shipping
if(isset($filteredShippingRanges) && $filteredShippingRanges->count() > 0){
    foreach($filteredShippingRanges as $range){
        $breakdown['shipping_options'][] = [
            'title' => $range->title,
            'price' => round($range->price, 2)
        ];
    }
}

// Turnaround
if(isset($filteredTurnaroundRanges) && $filteredTurnaroundRanges->count() > 0){
    foreach($filteredTurnaroundRanges as $range){
        $breakdown['turnaround_times'][] = [
            'title' => $range->title,
            'days' => $range->days
        ];
    }
}




        // --- ধাপ ৫: নতুন স্ট্রাকচারে রেসপন্স তৈরি করুন ---
        $response = [
            'data' => [
                'breakdown' => $breakdown,

                // 'breakdown' => [
                //     'Message' => $PriceConfigMessage,
                //     'quantity' => (float)$quantity,
                //     'configuration_price' => (float)$configurationPrice,
                //     'configuration_price_into_quantity_price' => $configuration_price_into_quantity_price,
                //     'per_sq_ft_price' => (float)$price_per_sq_ft,
                //     'total_sq_ft' => (float)$total_sq_ft,
                //     'total_sq_ft_price' => isset($params['sq_ft']) ? (float)$params['sq_ft'] : null,
                //     'total_sq_ft_price' => (float)$price_for_sq_ft,
                //     'quantity_into_total_sq_ft_price' => $quantity_into_total_sq_ft_price ?? 0,
                //     'configuration_price_into_quantity_price_plus_quantity_into_total_sq_ft_price' => (float)$finalPrice,
                // ],


                'breakdown_message' => $breakdownMessage,
                'quantity' => $quantity,
                'price_config_list' => $priceConfigList,
                'job_sample_price' => number_format($product->job_sample_price, 2),
                'digital_proof_price' => number_format($product->dital_proof_price, 2),
            ]
        ];

        // কোয়ান্টিটি অনুযায়ী শিপিং রেঞ্জ ফিল্টার করুন এবং keys রিসেট করুন
        $filteredShippingRanges = $product->shippingRanges->filter(function ($range) use ($quantity) {
            return $quantity >= $range->min_quantity &&
                ($range->max_quantity === null || $quantity <= $range->max_quantity);
        })->values(); // <-- এখানে values() যোগ করুন

        // কোয়ান্টিটি অনুযায়ী টার্নআরাউন্ড রেঞ্জ ফিল্টার করুন এবং keys রিসেট করুন
        $filteredTurnaroundRanges = $product->turnaroundRanges->filter(function ($range) use ($quantity) {
            return $quantity >= $range->min_quantity &&
                ($range->max_quantity === null || $quantity <= $range->max_quantity);
        })->values(); // <-- এখানেও values() যোগ করুন

        // ফিল্টার করা রেঞ্জগুলো রেসপন্সে যোগ করুন
        $response['data']['shippings'] = $filteredShippingRanges;
        $response['data']['turnarounds'] = $filteredTurnaroundRanges;

        return $response;
    }



}
