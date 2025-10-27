<?php

namespace App\Http\Controllers\Global\Products;
use App\Models\Product;

use Illuminate\Http\Request;
use App\Helpers\HelpersFunctions;
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
        $products = Product::with(['category:id,name','images'])
            ->where('active', true)
            ->when($request->category_id, function ($query, $categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->select('id', 'product_id', 'product_name', 'thumbnail', 'base_price', 'job_sample_price', 'digital_proof_price', 'active', 'popular_product','category_id')
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



/**
 * Get pricing for a specific product based on provided parameters.
 */
public function getPrice(Request $request, $productId)
{
    // ১. ইনকামিং ডেটা ভ্যালিডেট করুন
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

    // ২. ভ্যালিডেটেড ডেটা একটি অ্যারেতে নিন
    // $request->only() শুধুমাত্র প্রয়োজনীয় কীগুলো সহ একটি অ্যারে তৈরি করে।
    // এটি আপনার হেলপার ফাংশনকে সরাসরি Request অবজেক্টের পরিবর্তে একটি পরিষ্কার অ্যারে দেয়।
    $pricingParams = $request->only(['runsize', 'options', 'sq_ft', 'product_type']);

    // ৩. হেলপার ফাংশনকে অ্যারে এবং productId পাস করুন
    // এখন আপনার HelpersFunctions ক্লাসের getPricingData মেথডটিকে এমনভাবে ডিজাইন করতে হবে
    // যেটি একটি অ্যারে এবং productId আশা করে।
    $getPricingData = HelpersFunctions::getPricingData($pricingParams, $productId);

    return response()->json($getPricingData);
}





}
