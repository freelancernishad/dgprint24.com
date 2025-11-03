<?php

namespace App\Http\Controllers\Postgres;
use App\Models\Tax;
use App\Models\Product;
use App\Models\Category;
use App\Models\Shipping;
use Illuminate\Http\Request;
use App\Models\Postgres\PgTax;
use App\Models\TurnAroundTime;
use App\Models\Postgres\PgProduct;
use Illuminate\Support\Facades\DB;
use App\Models\Postgres\PgCategory;
use App\Models\Postgres\PgShipping;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Postgres\PgTurnAroundTime;

class PostgresController extends Controller
{
    // 🔹 Load all categories from PostgreSQL
    public function GetCategories()
    {
        $categories = PgCategory::all();


        foreach ($categories as $category) {
            $category = Category::firstOrCreate(
                ['category_id' => $category->category_id],
                [

                    'name' => $category->categoryName,
                    'category_id' => $category->categoryId,
                    'category_description' => $category->description,
                    'category_image' => $category->imageLink,
                    'variants' => json_decode($category->varients),
                    'tags' => json_decode($category->tags),
                    'active' => $category->active,
                    'show_in_navbar' => $category->showInNavbar,

                ]

            );
        }

        return response()->json($categories);
    }


    public function GetShipping()
    {
        $shippings = PgShipping::all();

        // return response()->json($shippings);

        foreach ($shippings as $shipping) {
            $shipping = Shipping::firstOrCreate(
                ['shipping_label' => $shipping->shippingLabel,'note' => $shipping->note],
                [
                    'category_name' => $shipping->categoryName,
                    'category_id' => $shipping->categoryId,
                    'shipping_label' => $shipping->shippingLabel,
                    'shipping_value' => $shipping->shippingValue,
                    'price' => $shipping->price,
                    'note' => $shipping->note,
                    'runsize' => $shipping->runsize,

                ]

            );
        }

        return response()->json($shippings);
    }

    public function GetTurnAroundTime()
    {
        $turnAroundTimes = PgTurnAroundTime::all();

        // return response()->json($turnAroundTimes);

        foreach ($turnAroundTimes as $turnAroundTime) {
            $turnAroundTime = TurnAroundTime::firstOrCreate(
                ['turnaround_label' => $turnAroundTime->label,'note' => $turnAroundTime->note],
                [
                    'name'=> $turnAroundTime->turnaroundLabel,
                    'category_name'=> $turnAroundTime->categoryName,
                    'category_id'=> $turnAroundTime->categoryId,
                    'turnaround_label'=> $turnAroundTime->turnaroundLabel,
                    'turnaround_value'=> $turnAroundTime->turnaroundValue,
                    'price'=> $turnAroundTime->price,
                    'discount'=> $turnAroundTime->discount,
                    'note'=> $turnAroundTime->note,
                    'runsize'=> $turnAroundTime->runsize,

                ]

            );
        }

        return response()->json($turnAroundTimes);
    }

    public function GetTax()
    {
        $Taxs = PgTax::all();

        // return response()->json($Taxs);

        foreach ($Taxs as $tax) {
            $tax = Tax::firstOrCreate(
                ['country' => $tax->country,'state' => $tax->state],
                [
                    'country' => $tax->country,
                    'state' => $tax->state,
                    'price' => $tax->price,

                ]

            );
        }

        return response()->json($Taxs);
    }


    public function GetProducts()
    {
        $Products = PgProduct::all();



        // return response()->json($Products);

        $index = 0;
        foreach ($Products as $product) {

            // skip 2 item
            if ($index < 2) {
                $index++;
                continue;
            }

            return response()->json($product);


            $productOptions = json_decode($product->productOptions, true);
            // return response()->json($productOptions);

            $faqs = $productOptions['faqs'] ?? [];
            $priceConfigs = $productOptions['priceConfig'] ?? [];





            $advanceOptions = $productOptions['advanceOptions'] ?? [];
            $dynamicOptions = $productOptions['dynamicOptions'] ?? [];





         $product = [
            'product_id' => $product->productId,
            'product_name' => $product->productName,
            'product_description' => $product->productDescription,
            'active' => $product->active,
            'popular_product' => $product->popularProduct,
            'thumbnail' => $product->thumbnail,
            // 'images' => json_decode($product->images),
            'base_price' => $product->basePrice,
            'category_id' => Category::where('category_id', $product->categoryId)->first()->id,

            'faqs' => $faqs,
            // 'priceConfigurations' => $priceConfig,
            'dynamicOptions' => $dynamicOptions,

            'product_type' => $advanceOptions['productType'],
            'job_sample_price' => $advanceOptions['jobSamplePrice'],
            'digital_proof_price' => $advanceOptions['digitalProofPrice'],
         ];


         $product = Product::firstOrCreate(
            ['product_id' => $product['product_id']],
            $product
         );


         $priceConfigurations = [];
         foreach ($priceConfigs as $priceConfig) {
                $priceConfigurations[] = [
                    'product_id'=>null,
                    'runsize' => $priceConfig['runsize'] ?? null,
                    'price' => $priceConfig['price'] ?? null,
                    'discount' => $priceConfig['discount'] ?? null,
                    'options' => $priceConfig['options'] ?? null,
                    'shippings' => $priceConfig['shippings'] ?? null,
                    'turnarounds' => $priceConfig['turnarounds'] ?? null,
                ];
         }

         $product->priceConfigurations()->createMany($priceConfigurations);

        $priceConfigShipings = [];
        $priceConfigTurnarounds = [];
         foreach ($priceConfigs as $priceConfig) {
            if (isset($priceConfig['shippings'])) {
                foreach ($priceConfig['shippings'] as $shipping) {
                    $priceConfigShipings[] = [
                        'price_configuration_id' => null,
                        'shipping_label' => $shipping['shippingLabel'] ?? null,
                        'shipping_value' => $shipping['shippingValue'] ?? null,
                        'price' => $shipping['price'] ?? null,
                    ];
                }
            }

            if (isset($priceConfig['turnarounds'])) {
                foreach ($priceConfig['turnarounds'] as $turnaround) {
                    $priceConfigTurnarounds[] = [
                        'price_configuration_id' => null,
                        'turnaround_label' => $turnaround['turnaroundLabel'] ?? null,
                        'turnaround_value' => $turnaround['turnaroundValue'] ?? null,
                        'price' => $turnaround['price'] ?? null,
                        'discount' => $turnaround['discount'] ?? null,
                    ];
                }
            }
            }


            return response()->json(['data' => $priceConfigShipings]);

         $product->priceConfigurations()->shippings()->createMany($priceConfigShipings);
         $product->priceConfigurations()->turnarounds()->createMany($priceConfigTurnarounds);



         return response()->json($product);




            return response()->json(['data' => $priceConfigurations]);







         return response()->json($product);




        }

        return response()->json($Products);
    }



    public function transferAllProducts()
    {
        // সব PgProduct ডেটা নিয়ে আসুন
        $pgProducts = PgProduct::all();

        $transferredCount = 0;
        $failedCount = 0;
        $errors = [];

        // প্রতিটি প্রোডাক্টের জন্য ট্রান্সফার প্রক্রিয়া চালান
        foreach ($pgProducts as $pgProduct) {
            try {
                $this->transferSingleProduct($pgProduct);
                $transferredCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'product_id' => $pgProduct->productId,
                    'error' => $e->getMessage()
                ];
                Log::error("Failed to transfer product {$pgProduct->productId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Product transfer process completed.',
            'transferred' => $transferredCount,
            'failed' => $failedCount,
            'errors' => $errors
        ]);
    }



    /**
     * একটি নির্দিষ্ট PgProduct কে নতুন স্ট্রাকচারে ট্রান্সফার করে।
     * এই ফাংশনটি ডাটাবেস ট্রানজেকশন ম্যানেজ করে।
     */
    public function transferSingleProduct(PgProduct $pgProduct)
    {
        DB::beginTransaction();
        try {
            // ১. JSON ডেটা ডিকোড করুন
            $productOptions = json_decode($pgProduct->productOptions, true);
            $images = json_decode($pgProduct->images, true) ?? [];

            // ২. মূল প্রোডাক্ট তৈরি করুন
            $product = $this->createMainProduct($pgProduct, $productOptions);

            // ৩. সম্পর্কিত (related) ডেটা তৈরি করার জন্য আলাদা ফাংশন কল করুন
            $this->createPriceRanges($product, $productOptions['advanceOptions']['advancedOptions']['priceRanges'] ?? []);
            $this->createTurnaroundRanges($product, $productOptions['advanceOptions']['advancedOptions']['turnaroundRange'] ?? []);
            $this->createShippingRanges($product, $productOptions['advanceOptions']['advancedOptions']['shippingRange'] ?? []);
            $this->createDimensionPricing($product, $productOptions['advanceOptions']['advancedOptions'] ?? []);
            $this->createProductImages($product, $images);
            $this->createFaqs($product, $productOptions['faqs'] ?? []);
            $this->createPriceConfigurations($product, $productOptions['priceConfig'] ?? []);

            DB::commit();
            Log::info("Successfully transferred product: {$pgProduct->productId}");

        } catch (\Exception $e) {
            DB::rollBack();
            // এররটি উপরের ফাংশনে পাঠানো হবে যেন সেটা লগ করা যায়
            throw new \Exception("Transfer failed for product {$pgProduct->productId}. Reason: " . $e->getMessage());
        }
    }

    /**
     * মূল প্রোডাক্ট রেকর্ড তৈরি করে।
     */
    private function createMainProduct(PgProduct $pgProduct, array $productOptions): Product
    {
        $advanceOptions = $productOptions['advanceOptions'] ?? [];

        // PgProduct এর categoryId দিয়ে নতুন Category টেবিলের ID বের করুন
        $category = Category::where('category_id', $pgProduct->categoryId)->first();
        if (!$category) {
            throw new \Exception("Category with ID {$pgProduct->categoryId} not found.");
        }

        return Product::create([
            'product_id' => $pgProduct->productId, // PgProduct এর productId ব্যবহার
            'category_id' => $category->id,
            'product_name' => $pgProduct->productName,
            'product_type' => $advanceOptions['productType'] ?? null,
            'product_description' => $pgProduct->productDescription,
            'dynamicOptions' => $productOptions['dynamicOptions'] ?? null,
            'active' => $pgProduct->active,
            'popular_product' => $pgProduct->popularProduct,
            'thumbnail' => $pgProduct->thumbnail,
            'base_price' => $pgProduct->basePrice,
            'job_sample_price' => $advanceOptions['jobSamplePrice'] ?? 0,
            'digital_proof_price' => $advanceOptions['digitalProofPrice'] ?? 0,
        ]);
    }

    /**
     * প্রাইস রেঞ্জ রেকর্ডগুলো তৈরি করে।
     */
    private function createPriceRanges(Product $product, array $priceRangesData)
    {
        foreach ($priceRangesData as $rangeData) {
            $product->priceRanges()->create([
                'min_quantity' => $rangeData['minQuantity'] ?? 0,
                'max_quantity' => $rangeData['maxQuantity'] ?? 0,
                'price_per_sq_ft' => $rangeData['pricePerSqFt'] ?? 0,
            ]);
        }
    }

    /**
     * টার্নআরাউন্ড রেঞ্জ রেকর্ডগুলো তৈরি করে।
     */
    private function createTurnaroundRanges(Product $product, array $turnaroundRangesData)
    {
        foreach ($turnaroundRangesData as $turnaroundRangeData) {
            $product->turnaroundRanges()->create([
                'min_quantity' => $turnaroundRangeData['minQuantity'] ?? 0,
                'max_quantity' => $turnaroundRangeData['maxQuantity'] ?? 0,
                'discount' => $turnaroundRangeData['discount'] ?? 0,
                'turnarounds' => $turnaroundRangeData['turnarounds'] ?? [],
            ]);
        }
    }

    /**
     * শিপিং রেঞ্জ রেকর্ডগুলো তৈরি করে।
     */
    private function createShippingRanges(Product $product, array $shippingRangesData)
    {
        foreach ($shippingRangesData as $shippingRangeData) {
            $product->shippingRanges()->create([
                'min_quantity' => $shippingRangeData['minQuantity'] ?? 0,
                'max_quantity' => $shippingRangeData['maxQuantity'] ?? 0,
                'discount' => $shippingRangeData['discount'] ?? 0,
                'shippings' => $shippingRangeData['shippings'] ?? [],
            ]);
        }
    }

    /**
     * ডাইমেনশন প্রাইসিং রেকর্ড তৈরি করে।
     */
    private function createDimensionPricing(Product $product, array $dimensionPricingData)
    {
        if (empty($dimensionPricingData)) {
            return;
        }
        $product->dimensionPricing()->create([
            'minwidth' => (float)($dimensionPricingData['minwidth'] ?? 0),
            'maxwidth' => isset($dimensionPricingData['maxwidth']) ? (float)$dimensionPricingData['maxwidth'] : 0,
            'minheight' => (float)($dimensionPricingData['minheight'] ?? 0),
            'maxheight' => isset($dimensionPricingData['maxheight']) ? (float)$dimensionPricingData['maxheight'] : 0,
            'basePricePerSqFt' => number_format((float)($dimensionPricingData['basePricePerSqFt'] ?? 0), 2, '.', '')
        ]);
    }

    /**
     * প্রোডাক্ট ইমেজ রেকর্ডগুলো তৈরি করে।
     */
    private function createProductImages(Product $product, array $imagesData)
    {
        foreach ($imagesData as $index => $imageUrl) {
            $product->images()->create(['image_url' => $imageUrl, 'sort_order' => $index]);
        }
    }

    /**
     * FAQ রেকর্ডগুলো তৈরি করে।
     */
    private function createFaqs(Product $product, array $faqsData)
    {
        foreach ($faqsData as $index => $faq) {
            $product->faqs()->create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'sort_order' => $index
            ]);
        }
    }

    /**
     * প্রাইস কনফিগারেশন এবং এর সাথে সম্পর্কিত শিপিং ও টার্নআরাউন্ড তৈরি করে।
     */
    private function createPriceConfigurations(Product $product, array $priceConfigsData)
    {
        foreach ($priceConfigsData as $configData) {
            // অপশনগুলো ফ্ল্যাট করুন (store ফাংশনের মতো)
            $flatOptions = [];
            foreach ($configData['options'] as $key => $value) {
                if (isset($value['selected'])) {
                    $flatOptions[$key] = $value['selected'];
                } else {
                    $flatOptions[$key] = $value;
                }
            }

            // প্রাইস কনফিগারেশন তৈরি করুন
            $priceConfig = $product->priceConfigurations()->create([
                'runsize' => $configData['runsize'],
                'price' => $configData['price'],
                'discount' => $configData['discount'] ?? 0,
                'options' => $flatOptions,
            ]);

            // এই কনফিগারেশনের জন্য শিপিং তৈরি করুন
            $shippingsToCreate = [];
            foreach ($configData['shippings'] ?? [] as $shippingData) {
                $shippingsToCreate[] = [
                    'shipping_id' => $shippingData['id'] ?? null,
                    'shippingLabel' => $shippingData['shippingLabel'],
                    'shippingValue' => $shippingData['shippingValue'],
                    'price' => $shippingData['price'],
                    'note' => $shippingData['note'] ?? null,
                ];
            }
            if (!empty($shippingsToCreate)) {
                $priceConfig->shippings()->createMany($shippingsToCreate);
            }

            // এই কনফিগারেশনের জন্য টার্নআরাউন্ড তৈরি করুন
            $turnaroundsToCreate = [];
            foreach ($configData['turnarounds'] ?? [] as $turnaroundData) {
                $turnaroundsToCreate[] = [
                    'turnaround_id' => $turnaroundData['id'] ?? null,
                    'turnaroundLabel' => $turnaroundData['turnaroundLabel'],
                    'turnaroundValue' => $turnaroundData['turnaroundValue'],
                    'price' => $turnaroundData['price'] ?? 0,
                    'note' => $turnaroundData['note'] ?? null,
                ];
            }
            if (!empty($turnaroundsToCreate)) {
                $priceConfig->turnarounds()->createMany($turnaroundsToCreate);
            }
        }
    }



}
