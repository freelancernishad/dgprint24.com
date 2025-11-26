<?php

namespace App\Http\Controllers\Admin\Products;
use App\Models\Faq;

use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Helpers\HelpersFunctions;
use App\Models\PriceConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\PriceConfigurationShipping;
use App\Models\PriceConfigurationTurnaround;

class AdminProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * অ্যাডমিন প্যানেলে সব প্রোডাক্টের লিস্ট দেখানোর জন্য।
     */
    public function index()
    {
        $products = Product::with('category:id,name','faqs','images')
            ->select('id', 'product_id', 'product_name', 'category_id', 'active', 'popular_product','dynamicOptions','extraDynamicOptions', 'created_at')
            ->latest()
            ->paginate(20);

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     * নতুন প্রোডাক্ট তৈরি করার জন্য মূল ফাংশন।
     */
    public function store(Request $request)
    {
        // পরিবর্তন ১: 'productOptions' এর ভ্যালিডেশন রুলটি 'json' থেকে 'array' এ পরিবর্তন করুন
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'categoryId' => 'required|exists:categories,id',
            'productName' => 'required|string|max:255',
            'productDescription' => 'nullable|string',
            'productOptions' => 'required|array', // <--- এখানে পরিবর্তন করুন
            'productImages' => 'nullable|array',
            'productImages.*' => 'url',
            'priceConfig' => 'required|array|min:1',
            'priceConfig.*.runsize' => 'required|integer|min:1',
            'priceConfig.*.price' => 'required|numeric|min:0',
            'priceConfig.*.discount' => 'nullable|numeric|min:0|max:100',
            'priceConfig.*.options' => 'required|array',
            'priceConfig.*.shippings' => 'required|array',
            'priceConfig.*.turnarounds' => 'required|array',


            'jobSamplePrice' => 'nullable|numeric|min:0',
            'digitalProofPrice' => 'nullable|numeric|min:0',


        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        Log::info($validatedData['productOptions']);

        DB::beginTransaction();
        try {
            // মূল প্রোডাক্ট তৈরি
            $product = Product::create([
                'product_id' => 'product-' . Str::uuid(),
                'category_id' => $validatedData['categoryId'],
                'product_name' => $validatedData['productName'],
                'product_type' => $validatedData['productOptions']['advanceOptions']['productType'],
                'product_description' => $validatedData['productDescription'],
                // পরিবর্তন ২: ডাটাবেসে সেভ করার সময় অ্যারেকে JSON স্ট্রিং-এ কনভার্ট করুন
                'dynamicOptions' => $validatedData['productOptions']['dynamicOptions'] ?? null,
                'extraDynamicOptions' => $validatedData['productOptions']['extraDynamicOptions'] ?? null,
                'active' => true,
                'job_sample_price' => $validatedData['jobSamplePrice'] ?? 0,
                'digital_proof_price' => $validatedData['digitalProofPrice'] ?? 0,
            ]);



            // প্রাইজ রেঞ্জ গুলো সেভ করুন

        // প্রাইজ রেঞ্জ গুলো সেভ করুন (এখানে ডট নোটেশন পাথ ব্যবহার করে ডেটা বের করা হচ্ছে)

            if (isset($validatedData['productOptions']['advanceOptions']['advancedOptions']['priceRanges']) && is_array($validatedData['productOptions']['advanceOptions']['advancedOptions']['priceRanges'])) {

                $priceRanges = $validatedData['productOptions']['advanceOptions']['advancedOptions']['priceRanges'] ?? [];
                foreach ($priceRanges as $rangeData) {
                    $product->priceRanges()->create([
                        'min_quantity' => $rangeData['minQuantity'],
                        'max_quantity' => $rangeData['maxQuantity'] ?? null,
                        'price_per_sq_ft' => $rangeData['pricePerSqFt'],
                    ]);
                }



            }






                // নতুন কোড: TurnaroundRange সেভ করুন
        if (isset($validatedData['productOptions']['advanceOptions']['advancedOptions']['turnaroundRange']) && is_array($validatedData['productOptions']['advanceOptions']['advancedOptions']['turnaroundRange'])) {

            $turnaroundRanges = $validatedData['productOptions']['advanceOptions']['advancedOptions']['turnaroundRange'] ?? [];

            foreach ($turnaroundRanges as $turnaroundRangeData) {
                $turnaroundRange = $product->turnaroundRanges()->create([
                    'min_quantity' => $turnaroundRangeData['minQuantity'],
                    'max_quantity' => $turnaroundRangeData['maxQuantity'] ?? null,
                    'discount' => $turnaroundRangeData['discount'] ?? 0,
                    'turnarounds' => $turnaroundRangeData['turnarounds'] ?? [],
                ]);
            }
        }

        // নতুন কোড: ShippingRange সেভ করুন
        if (isset($validatedData['productOptions']['advanceOptions']['advancedOptions']['shippingRange']) && is_array($validatedData['productOptions']['advanceOptions']['advancedOptions']['shippingRange'])) {

            $shippingRanges = $validatedData['productOptions']['advanceOptions']['advancedOptions']['shippingRange'] ?? [];

            foreach ($shippingRanges as $shippingRangeData) {
                $shippingRange = $product->shippingRanges()->create([
                    'min_quantity' => $shippingRangeData['minQuantity'],
                    'max_quantity' => $shippingRangeData['maxQuantity'] ?? null,
                    'discount' => $shippingRangeData['discount'] ?? 0,
                    'shippings' => $shippingRangeData['shippings'] ?? [],
                ]);
            }
        }



        if (isset($validatedData['productOptions']['advanceOptions']['advancedOptions'])) {
            // ডাটাটিকে একটি ভেরিয়েবলে রাখুন, যাতে বারবার লিখতে না হয়
            $dimensionPricingsData = $validatedData['productOptions']['advanceOptions']['advancedOptions'] ?? [];
            // এখন নিরাপদে ডাটা অ্যাক্সেস করুন
            $product->dimensionPricing()->create([
                'product_id' => $product->id, // এটি আসলে লাগবে না, Laravel নিজেই যোগ করে
                'minwidth' => (float)($dimensionPricingsData['minwidth'] ?? 0),
                'maxwidth' => isset($dimensionPricingsData['maxwidth']) ? (float)$dimensionPricingsData['maxwidth'] : null,
                'minheight' => (float)($dimensionPricingsData['minheight'] ?? 0),
                'maxheight' => isset($dimensionPricingsData['maxheight']) ? (float)$dimensionPricingsData['maxheight'] : null,
                'basePricePerSqFt' => number_format((float)($dimensionPricingsData['basePricePerSqFt'] ?? 0), 2, '.', '')
            ]);

        }






            // প্রোডাক্ট ইমেজ সেভ
            if (!empty($validatedData['productImages'])) {
                foreach ($validatedData['productImages'] as $index => $imageUrl) {
                    $product->images()->create(['image_url' => $imageUrl, 'sort_order' => $index]);
                }
            }

            // পরিবর্তন ৩: আর json_decode করার দরকার নেই, কারণ ডেটা আগে থেকেই একটা অ্যারে
            $productOptions = $validatedData['productOptions']; // <--- এখানে পরিবর্তন করুন
            if (isset($productOptions['faqs']) && is_array($productOptions['faqs'])) {
                foreach ($productOptions['faqs'] as $index => $faq) {
                    $product->faqs()->create(['question' => $faq['question'], 'answer' => $faq['answer'], 'sort_order' => $index]);
                }
            }

            // প্রাইজ কনফিগারেশন, শিপিং এবং টার্নআরাউন্ড সেভ
            foreach ($validatedData['priceConfig'] as $configData) {






            $helpers = new HelpersFunctions();
            $flatOptions = $helpers->flattenSelectedOptions($configData['options']);

            Log::info('Flattened Options: ', $flatOptions);


            // $flatOptions = [];
            // foreach ($configData['options'] as $key => $value) {
            //     // ধরুন, $key = 'SIDE', $value = ['selected' => '4/0 ONE SIDE']
            //     // আমরা শুধু 'selected' এর ভ্যালু নিব
            //     if (isset($value['selected'])) {
            //         $flatOptions[$key] = $value['selected'];
            //     }else {
            //         $flatOptions[$key] = $value; // অথবা অন্য কোনো ডিফল্ট ভ্যালু
            //     }
            // }





            // --- নতুন লজিক শেষ ---

            $priceConfig = $product->priceConfigurations()->create([
                'runsize' => $configData['runsize'],
                'price' => $configData['price'],
                'discount' => $configData['discount'] ?? 0,
                'options' => $flatOptions, // <--- ফ্ল্যাট অ্যারেটি সেভ করুন
            ]);


            // 3️⃣ Save each flattened option relationally
            foreach ($flatOptions as $key => $value) {
                $priceConfig->optionsRel()->create([
                    'key' => $key,
                    'value' => $value,
                ]);
            }


                foreach ($configData['shippings'] as $shippingData) {
                    $priceConfig->shippings()->create([
                        'shipping_id' => $shippingData['shipping_id'] ?? null,
                        'shippingLabel' => $shippingData['shippingLabel'],
                        'shippingValue' => $shippingData['shippingValue'],
                        'price' => $shippingData['price'],
                        'note' => $shippingData['note'] ?? null,
                    ]);
                }

                foreach ($configData['turnarounds'] as $turnaroundData) {
                    $priceConfig->turnarounds()->create([
                        'turnaround_id' => $turnaroundData['turnaround_id'] ?? null,
                        'turnaroundLabel' => $turnaroundData['turnaroundLabel'],
                        'turnaroundValue' => $turnaroundData['turnaroundValue'],
                        'price' => $turnaroundData['price'] ?? 0,
                        'note' => $turnaroundData['note'] ?? null,
                    ]);
                }
            }

            DB::commit();
            // সফলভাবে তৈরি হওয়া প্রোডাক্টটি রিলেশনসহ লোড করে রিটার্ন করুন
            $newProduct = Product::with(['category', 'faqs', 'images', 'priceConfigurations.shippings', 'priceConfigurations.turnarounds','dimensionPricing'])->find($product->id);
            return response()->json($newProduct, 201);

        } catch (\Exception $e) {
            Log::error('Product creation failed: ' . $e->getMessage());
            DB::rollBack();
            return response()->json(['error' => $e], 500);
        }
    }

    /**
     * Display the specified resource.
     * কোনো প্রোডাক্ট এডিট করার জন্য ডেটা আনার জন্য।
     */
    public function show($id)
    {
        $product = Product::with([
            'category:id,name,category_id',
            'faqs',
            'images',
            'priceConfigurations.shippings',
            'priceConfigurations.turnarounds'
        ])->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     * প্রোডাক্ট আপডেট করার জন্য।
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $validatedData = $request->validate([
            'categoryId' => 'required|exists:categories,id',
            'productName' => 'required|string|max:255',
            'productDescription' => 'nullable|string',
            'productOptions' => 'required|json',
            'productImages' => 'nullable|array',
            'productImages.*' => 'url',
            'priceConfig' => 'required|array|min:1',
            // ... অন্যান্য ভ্যালিডেশন rules store মেথডের মতোই ...
        ]);

        DB::beginTransaction();
        try {
            // মূল প্রোডাক্ট আপডেট
            $product->update([
                'category_id' => $validatedData['categoryId'],
                'product_name' => $validatedData['productName'],
                'product_description' => $validatedData['productDescription'],
                'product_options' => $validatedData['productOptions'],
            ]);

            // পুরানো রিলেটেড ডেটা ডিলিট করুন (cascade delete কাজ করবে)
            $product->faqs()->delete();
            $product->images()->delete();
            $product->priceConfigurations()->delete(); // এটা shippings এবং turnarounds ও ডিলিট করবে

            // নতুন ডেটা ইনসার্ট করুন (store মেথডের লজিকের মতো)
            if (!empty($validatedData['productImages'])) {
                foreach ($validatedData['productImages'] as $index => $imageUrl) {
                    $product->images()->create(['image_url' => $imageUrl, 'sort_order' => $index]);
                }
            }
            $productOptions = json_decode($validatedData['productOptions'], true);
            if (isset($productOptions['faqs'])) {
                foreach ($productOptions['faqs'] as $index => $faq) {
                    $product->faqs()->create(['question' => $faq['question'], 'answer' => $faq['answer'], 'sort_order' => $index]);
                }
            }

        // প্রাইজ কনফিগারেশন, শিপিং এবং টার্নআরাউন্ড সেভ
        foreach ($validatedData['priceConfig'] as $configData) {

            // --- নতুন লজিক: options অ্যারেকে ফ্ল্যাট করুন ---
            $flatOptions = [];
            foreach ($configData['options'] as $key => $value) {
                // ধরুন, $key = 'SIDE', $value = ['selected' => '4/0 ONE SIDE']
                // আমরা শুধু 'selected' এর ভ্যালু নিব
                if (isset($value['selected'])) {
                    $flatOptions[$key] = $value['selected'];
                }else {
                    $flatOptions[$key] = $value; // অথবা অন্য কোনো ডিফল্ট ভ্যালু
                }
            }
            // --- নতুন লজিক শেষ ---

            $priceConfig = $product->priceConfigurations()->create([
                'runsize' => $configData['runsize'],
                'price' => $configData['price'],
                'discount' => $configData['discount'] ?? 0,
                'options' => $flatOptions, // <--- ফ্ল্যাট অ্যারেটি সেভ করুন
            ]);

            foreach ($configData['shippings'] as $shippingData) { /* ... শিপিং ইনসার্ট লজিক ... */ }
            foreach ($configData['turnarounds'] as $turnaroundData) { /* ... টার্নআরাউন্ড ইনসার্ট লজিক ... */ }
        }

            DB::commit();
            return response()->json($product->load(['category', 'faqs', 'images', 'priceConfigurations']));

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Product could not be updated. Please try again.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * প্রোডাক্ট ডিলিট করার জন্য।
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        // ডাটাবেসে foreign key এ 'onDelete("cascade")' থাকায়,
        // প্রোডাক্ট ডিলিট করলে এর সাথে সম্পর্কিত সব ডেটা অটোমেটিক ডিলিট হয়ে যাবে।
        $product->delete();
        return response()->json(null, 204);
    }


      /**
     * Toggle the 'popular_product' status of a specific product.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePopular(Product $product)
    {
        // বর্তমান ভ্যালুটি নিন এবং তা উল্টে দিন (invert)
        // true হলে false হবে, false হলে true হবে
        $product->popular_product = !$product->popular_product;

        // পরিবর্তনগুলো ডাটাবেসে সেভ করুন
        $product->save();

        // সফলতার JSON রেসপন্স পাঠান
        return response()->json([
            'success' => true,
            'message' => 'Product popularity status updated successfully!',
            'data' => $product
        ]);
    }


}
