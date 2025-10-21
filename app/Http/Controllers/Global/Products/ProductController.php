<?php

namespace App\Http\Controllers\Global\Products;
use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\PriceConfiguration;
use Illuminate\Http\Request;

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
public function getPrice(Request $request, $productId)
{
    // ১. প্রথমে প্রোডাক্টটা খুঁজে নিন
    $product = Product::where('product_id', $productId)->where('active', true)->firstOrFail();

    // ২. ইনকামিং ডেটা ভ্যালিডেট করুন (ডাইনামিকভাবে)
    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'runsize' => 'required|integer|min:1',
        'options' => 'required|array|min:1',
        'options.*' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $validated = $validator->validated();

    // ৩. ডাইনামিকভাবে কুয়েরি তৈরি করুন
    $query = PriceConfiguration::with(['shippings', 'turnarounds'])
        ->where('product_id', $product->id)
        ->where('runsize', $validated['runsize']);

    // ইনকামিং অপশনগুলোর উপর ভিত্তি করে কুয়েরিতে শর্ত যোগ করুন (whereRaw ব্যবহার করে)
    foreach ($validated['options'] as $key => $value) {
        // MySQL-এর জন্য JSON_EXTRACT ব্যবহার করুন
        // এটি SQL Injection প্রতিরোধ করার জন্য প্রিপেয়ার্ড স্টেটমেন্ট (?) ব্যবহার করে
        $query->whereRaw("JSON_EXTRACT(options, '$." . $key . "') = ?", [$value]);
    }

    // কুয়েরি চালান
    $priceConfig = $query->first();

    // ৪. যদি কনফিগারেশন না পাওয়া যায়
    if (!$priceConfig) {
        return response()->json([
            'error' => 'Price configuration not found for the selected options and quantity.'
        ], 404);
    }

    // ৫. JSON রেসপন্স পাঠান
    return response()->json($priceConfig);
}
}
