<?php

namespace App\Http\Controllers\Global\Products;
use App\Models\Product;

use Illuminate\Http\Request;
use App\Helpers\HelpersFunctions;
use App\Models\PriceConfiguration;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Category;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * ফ্রন্টএন্ডে সব অ্যাকটিভ প্রোডাক্টের লিস্ট দেখানোর জন্য।
     */
public function index(Request $request)
{
    $products = Product::with(['category:id,name', 'images'])
        ->where('active', true)

        // Category filter
        ->when($request->category_id, function ($query, $categoryId) {
            $query->where('category_id', $categoryId);
        })

        // Search filter
        ->when($request->search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'LIKE', "%{$search}%")
                  ->orWhere('product_id', 'LIKE', "%{$search}%");
            });
        })

        ->select(
            'id',
            'product_id',
            'product_name',
            'thumbnail',
            'base_price',
            'job_sample_price',
            'digital_proof_price',
            'active',
            'popular_product',
            'category_id'
        )
        ->latest()
        ->paginate(20);

    return response()->json($products);
}

    /**
     * Get products by category ID.
     * নির্দিষ্ট ক্যাটাগরি আইডি দিয়ে প্রোডাক্টের লিস্ট দেখানোর জন্য।
     */
    public function getByCategory(Request $request, $category_id)
    {
        $per_page = $request->input('per_page', 20);

        // category_id দিয়ে category খোঁজা
        $category = Category::where('category_id', $category_id)
            ->with('children') // children load
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        // parent + child category IDs
        $categoryIds = [$category->id];

        if ($category->children->isNotEmpty()) {
            $categoryIds = array_merge(
                $categoryIds,
                $category->children->pluck('id')->toArray()
            );
        }

        // products load
        $products = Product::with(['category:id,name', 'images'])
            ->where('active', true)
            ->whereIn('category_id', $categoryIds)
            ->select(
                'id',
                'product_id',
                'product_name',
                'product_description',
                'thumbnail',
                'base_price',
                'job_sample_price',
                'digital_proof_price',
                'active',
                'popular_product',
                'category_id'
            )
            ->latest()
            ->paginate($per_page);

        return response()->json($products);
    }



    /**
     * Display the specified resource.
     * একটি নির্দিষ্ট প্রোডাক্টের সম্পূর্ণ বিবরণ দেখানোর জন্য।
     */
    public function show($productId)
    {
        $product = Product::with([
            'category:id,name,parent_id',
            'category.parent:id,name',
            'faqs',
            'images',
            'dimensionPricing'
        ])
        ->where('active', true)
        ->where('product_id', $productId)
        ->firstOrFail();

        return response()->json($product);
    }



    public function mostPopular(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        // 10টি সর্বাধিক জনপ্রিয় এবং সক্রিয় প্রোডাক্ট আনা
        $popularProducts = Product::where('popular_product', true)
            ->where('active', true)
            ->orderBy('updated_at', 'desc')
            ->paginate($per_page);

        return response()->json([
            'success' => true,
            'message' => 'Most popular products retrieved successfully.',
            'data' => $popularProducts
        ]);
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
        'sq_ft' => 'nullable|numeric',
        'product_type' => 'nullable|string|min:1',
        'turn_around_times_id' => 'nullable|string',
        'shipping_id' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // ২. ভ্যালিডেটেড ডেটা একটি অ্যারেতে নিন
    // $request->only() শুধুমাত্র প্রয়োজনীয় কীগুলো সহ একটি অ্যারে তৈরি করে।
    // এটি আপনার হেলপার ফাংশনকে সরাসরি Request অবজেক্টের পরিবর্তে একটি পরিষ্কার অ্যারে দেয়।
    $pricingParams = $request->only(['runsize', 'options', 'sq_ft', 'product_type','turn_around_times_id','shipping_id']);

    // ৩. হেলপার ফাংশনকে অ্যারে এবং productId পাস করুন
    // এখন আপনার HelpersFunctions ক্লাসের getPricingData মেথডটিকে এমনভাবে ডিজাইন করতে হবে
    // যেটি একটি অ্যারে এবং productId আশা করে।
    $getPricingData = HelpersFunctions::getPricingData($pricingParams, $productId);

    return response()->json($getPricingData);
}





}
