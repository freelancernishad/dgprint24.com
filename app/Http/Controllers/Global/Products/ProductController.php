<?php

namespace App\Http\Controllers\Global\Products;
use App\Models\Product;

use Illuminate\Http\Request;
use App\Models\PriceConfiguration;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * ফ্রন্টএন্ডে সব অ্যাকটিভ প্রোডাক্টের লিস্ট দেখানোর জন্য।
     */
    public function index(Request $request)
    {
        $products = Product::with(['category:id,name'])
            ->where('active', true)
            ->when($request->category_id, function ($query, $categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->select('id', 'product_id', 'product_name', 'thumbnail', 'popular_product', 'category_id')
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    /**
     * Display the specified resource.
     * একটি নির্দিষ্ট প্রোডাক্টের সম্পূর্ণ বিবরণ দেখানোর জন্য।
     */
    public function show($productId)
    {
        $product = Product::with([
            'category:id,name',
            'faqs',
            'images',
            // শুধুমাত্র প্রাইজ কনফিগারেশনের লিস্ট আনুন, শিপিং/টার্নআরাউন্ড নয়
            'priceConfigurations' => function ($query) {
                $query->select('id', 'product_id', 'runsize', 'price', 'discount', 'options');
            }
        ])
        ->where('active', true)
        ->where('product_id', $productId)
        ->firstOrFail();

        return response()->json($product);
    }

    /**
     * নির্দিষ্ট অপশন সিলেক্ট করে প্রাইজ জানার জন্য বিশেষ ফাংশন।
     */
/**
 * একটি ইউনিফাইড ফাংশন যা জেনারেল এবং ব্যানার প্রোডাক্টের জন্য কাজ করে।ে পারে।
 * এটি নির্দিষ্ট অপশন কম্বিনেশনের জন্য একটা ফিক্সড মূল্য এবং পরিমাণের উপর ভিত্তিক প্রাইজ রেঞ্জের উপর মোট মূল্য যোগ করে।
 * চূড়ন্ত মূল্য = (কনফিগারেশন মূল্য) + (পরিমাণ-ভিত্তিক মূল্য)
 */




public function getPrice(Request $request, $productId)
{
    // ১. প্রথমে প্রোডাক্টটা খুঁজে নিন
    $product = Product::where('product_id', $productId)->where('active', true)->firstOrFail();

    // ২. ইনকামিং ডেটা ভ্যালিডেট করুন
    // 'options' ঐচ্ছিক, কারণ কিছু প্রোডাক্টের জন্য অপশন প্রয়োজন হয় না
    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'runsize' => 'required|integer|min:1',
        'options' => 'nullable|array',
        'options.*' => 'required|string',
        'sq_ft' => 'nullable|integer|min:1',
        'product_type' => 'nullable|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $validated = $validator->validated();
    $quantity = $validated['runsize'];
    $hasOptions = !empty($validated['options']);

    // ভেরিয়েবল ভ্যারিয়েবল ভ্যারিয়েবল মূল্য রাখার জন্য ভেরিয়েবল
    $configurationPrice = 0;
    $configuration_price_plus_quantity_price = 0;
    $finalPrice = 0;
    $priceConfigData = null; // সম্পূর্ণ কনফিগারেশন ডেটা রাখার জন্য

    $price_per_sq_ft = 0;
    $price_for_sq_ft = 0;
    $priceConfigList = [];

    // --- ধাপ ১: কনফিগারেশন মূল্য খুঁজে বের করুন ---
    // যদি ইউজার কোনো অপশন সিলেক্ট করে থাকে
    if ($hasOptions) {
        $query = PriceConfiguration::with(['shippings', 'turnarounds'])
            ->where('product_id', $product->id);
            // ->where('runsize', $quantity);

        foreach ($validated['options'] as $key => $value) {
            $query->whereRaw("JSON_EXTRACT(options, '$." . $key . "') = ?", [$value]);
        }

        $priceConfigList = $query->get();
        // if(isset($validated['product_type']) && ($validated['product_type'] == 'general')) {
        //     $priceConfigList = $query->get();
        //     Log::info('General Product Price Configurations: ', $priceConfigList->toArray());
        // }


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
        if (isset($validated['sq_ft'])) {
            $price_for_sq_ft = $validated['sq_ft'] * $priceRange->price_per_sq_ft;
            $price_per_sq_ft = $priceRange->price_per_sq_ft;

        }

        // পরিমাণ এবং প্রতি বর্গফুটের দাম গুণ করুন
        $quantity_into_total_sq_ft_price = $quantity * $price_for_sq_ft;
    }

    // --- ধাপ ৩: চূড়ন্ত মূল্য ক্যালকুলেট করুন ---
    $configuration_price_plus_quantity_price = $configurationPrice * $quantity;
    $finalPrice = $configuration_price_plus_quantity_price + $quantity_into_total_sq_ft_price;

    // --- ধাপ ৪: যদি কোনো ধরনের প্রাইস তথ্য না থাকে, তাহলে এরর দিন ---
    if ($configurationPrice == 0 && $quantity_into_total_sq_ft_price == 0) {
        return response()->json([
            'error' => 'Pricing information is not configured for this product or the selected options.'
        ], 404);
    }

    // --- ধাপ ৫: নতুন স্ট্রাকচারে রেসপন্স তৈরি করুন ---
    $response = [
        'final_price' => number_format($finalPrice, 2),
        'pricing_model' => 'combined',
        'details_pricing' => [
            'breakdown' => [
                'quantity' => (float)$quantity,
                'configuration_price' => (float)number_format($configurationPrice, 2),
                'configuration_price_plus_quantity_price' => (float)number_format($configuration_price_plus_quantity_price, 2),
                'per_sq_ft' => (float)number_format($price_per_sq_ft, 2),
                'total_sq_ft' => isset($validated['sq_ft']) ? (float)$validated['sq_ft'] : null,
                'total_sq_ft_price' => (float)number_format($price_for_sq_ft, 2),
                'quantity_into_total_sq_ft_price' => (float)number_format($quantity_into_total_sq_ft_price, 2),
                'configuration_price_plus_quantity_price_plus_quantity_into_total_sq_ft_price' => (float)number_format($finalPrice, 2),


            ],
            'quantity' => $quantity,
            'price_config_list' => $priceConfigList,
            'job_sample_price' => number_format($product->job_sample_price, 2),
            'digital_proof_price' => number_format($product->dital_proof_price, 2),
        ]
    ];








// কোয়ান্টিটি অনুযায়ী শিপিং রেঞ্জ ফিল্টার করুন
 $filteredShippingRanges = $product->shippingRanges->filter(function ($range) use ($quantity) {
    return $quantity >= $range->min_quantity &&
           ($range->max_quantity === null || $quantity <= $range->max_quantity);
});

// কোয়ান্টিটি অনুযায়ী টার্নআরাউন্ড রেঞ্জ ফিল্টার করুন
 $filteredTurnaroundRanges = $product->turnaroundRanges->filter(function ($range) use ($quantity) {
    return $quantity >= $range->min_quantity &&
           ($range->max_quantity === null || $quantity <= $range->max_quantity);
});

// ফিল্টার করা রেঞ্জগুলো রেসপন্সে যোগ করুন
 $response['details_pricing']['shippings'] = $filteredShippingRanges;
 $response['details_pricing']['turnarounds'] = $filteredTurnaroundRanges;


    return response()->json($response);
}





}
