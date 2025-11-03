<?php

namespace App\Helpers;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PriceConfiguration;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

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
        Log::info('Fetching pricing for Product ID:', ['product_id' => $productId,'product' => $product]);

        // প্যারামিটার থেকে ভ্যালুগুলো নিন
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
        $priceConfigList = [];

        // --- ধাপ ১: কনফিগারেশন মূল্য খুঁজে বের করুন ---
        // যদি ইউজার কোনো অপশন সিলেক্ট করে থাকে
        if ($hasOptions) {
            $query = PriceConfiguration::with(['shippings', 'turnarounds'])
                ->where('product_id', $product->id);

            //     $options = json_encode($params['options']);
            Log::info('Flat Options for Price Configuration:', ['options' => $params['options']]);
            Log::info('JSON Encoded Flat Options:', ['options' => json_encode($params['options'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

            // $query->where("options", $options);

            // foreach ($params['options'] as $key => $value) {

            //     Log::info('Filtering Price Config with Option:', ['key' => $key, 'value' => $value]);

            //     $query->whereRaw("JSON_EXTRACT(options, '$." . $key . "') = ?", [$value]);
            // }
$query->where('options',json_encode($params['options']));


            $priceConfigList = $query->get();

            // SQL কোডটি কি দেখা যাবে
            Log::info('SQL Query:', ['query' => $query->toSql(), 'bindings' => $query->getBindings()]);

            Log::info('Price Config List:', ['list' => $priceConfigList]);

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

            $priceConfig = $query->first();

            if ($priceConfig) {
                // ডাটাবেসে যে মূল্য সংরক্ষিত আছে সেটাই নিন
                $configurationPrice = $priceConfig->price;
                // ফ্রন্টএন্ডকে শিপিং এবং টার্নআরাউন্ড অপশন দেখানোর জন্য সম্পূর্ণ ডেটা রাখুন
                $priceConfigData = $priceConfig;
            }
        }

        // --- ধাপ ২: পরিমাণ-ভিত্তিক রেঞ্জ মূল্য খুঁজে বের করুন ---
        $priceRange = $product->priceRanges()
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
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
        $configuration_price_into_quantity_price = $configurationPrice * $quantity;
        $finalPrice = $configuration_price_into_quantity_price + ($quantity_into_total_sq_ft_price ?? 0);

        // --- ধাপ ৪: যদি কোনো ধরনের প্রাইস তথ্য না থাকে, তাহলে এরর দিন ---
        if ($configurationPrice == 0 && ($quantity_into_total_sq_ft_price ?? 0) == 0) {
            return [
                'error' => 'Pricing information is not configured for this product or the selected options.'
            ];
        }

        // --- ধাপ ৫: নতুন স্ট্রাকচারে রেসপন্স তৈরি করুন ---
        $response = [
            'data' => [
                'breakdown' => [
                    'quantity' => (float)$quantity,
                    'configuration_price' => (float)number_format($configurationPrice, 2),
                    'configuration_price_into_quantity_price' => (float)number_format($configuration_price_into_quantity_price, 2),
                    'per_sq_ft_price' => (float)number_format($price_per_sq_ft, 2),
                    'total_sq_ft' => (float)$total_sq_ft,
                    'total_sq_ft_price' => isset($params['sq_ft']) ? (float)$params['sq_ft'] : null,
                    'total_sq_ft_price' => (float)number_format($price_for_sq_ft, 2),
                    'quantity_into_total_sq_ft_price' => (float)number_format($quantity_into_total_sq_ft_price ?? 0, 2),
                    'configuration_price_into_quantity_price_plus_quantity_into_total_sq_ft_price' => (float)number_format($finalPrice, 2),
                ],
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
