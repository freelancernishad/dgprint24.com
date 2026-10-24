<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Helpers\HelpersFunctions;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Faq;
use App\Models\PriceConfiguration;
use App\Models\PriceConfigurationOption;
use App\Models\PriceConfigurationShipping;
use App\Models\PriceConfigurationTurnaround;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FourOverImportController extends Controller
{
    /**
     * Import a 4over parsed product payload directly into the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        // Support payload passed either wrapped in 'data', 'payload', or directly as root object
        $payload = $request->input('data', $request->input('payload', $request->all()));

        if (empty($payload) || !is_array($payload)) {
            return response()->json([
                'isError' => true,
                'message' => 'No product payload data received for import.',
            ], 422);
        }

        $productName = trim($payload['productName'] ?? '');
        if (!$productName) {
            return response()->json([
                'isError' => true,
                'message' => 'Product name is required for database import.',
            ], 422);
        }

        // 1. Resolve Category Safely
        $categoryInput = $payload['categoryId'] ?? null;
        $category = null;

        if (!empty($categoryInput)) {
            if (is_numeric($categoryInput)) {
                $category = Category::find($categoryInput);
            } else {
                $category = Category::where('category_id', $categoryInput)->first();
            }
        }

        // Fallback to first available category if not matched
        if (!$category) {
            $category = Category::where('active', true)->first() ?: Category::first();
        }

        if (!$category) {
            return response()->json([
                'isError' => true,
                'message' => 'No categories found in store. Please create at least one category first.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // 2. Clean dynamicOptions for product
            $rawDynamicOptions = $payload['productOptions']['dynamicOptions'] ?? [];
            $cleanDynamicOptions = $this->cleanDynamicOptions(is_array($rawDynamicOptions) ? $rawDynamicOptions : []);

            // 3. Create Core Product Record
            $advanceOptions = $payload['productOptions']['advanceOptions'] ?? [];
            $productType = $advanceOptions['productType'] ?? 'general';
            $jobSamplePrice = $advanceOptions['jobSamplePrice'] ?? 0;
            $digitalProofPrice = $advanceOptions['digitalProofPrice'] ?? 0;

            // Generate unique product_id and calculate serial
            $maxSerial = Product::where('category_id', $category->id)->max('serial') ?? 0;

            $product = Product::create([
                'product_id' => 'product-' . Str::uuid(),
                'category_id' => $category->id,
                'product_name' => $productName,
                'product_type' => $productType,
                'product_description' => $payload['productDescription'] ?? '',
                'thumbnail' => $payload['thumbnail'] ?? ($payload['productImages'][0] ?? null),
                'base_price' => $payload['basePrice'] ?? 0,
                'dynamicOptions' => $cleanDynamicOptions,
                'extraDynamicOptions' => $payload['productOptions']['extraDynamicOptions'] ?? null,
                'active' => true,
                'popular_product' => $payload['popularProduct'] ?? false,
                'serial' => $maxSerial + 1,
                'job_sample_price' => $jobSamplePrice,
                'digital_proof_price' => $digitalProofPrice,
            ]);

            // 4. Save Product Images
            $images = $payload['productImages'] ?? ($payload['images'] ?? []);
            if (is_array($images)) {
                foreach ($images as $index => $imageUrl) {
                    if (!empty($imageUrl) && is_string($imageUrl)) {
                        $product->images()->create([
                            'image_url' => $imageUrl,
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            // 5. Save FAQs
            $faqs = $payload['productOptions']['faqs'] ?? [];
            if (is_array($faqs)) {
                foreach ($faqs as $index => $faq) {
                    if (!empty($faq['question']) && !empty($faq['answer'])) {
                        $product->faqs()->create([
                            'question' => $faq['question'],
                            'answer' => $faq['answer'],
                            'sort_order' => $index,
                        ]);
                    }
                }
            }

            // 6. Save Price Configurations with Options, Shipping & Turnaround strictly from 4over
            $priceConfigs = $payload['priceConfig'] ?? [];
            if (!is_array($priceConfigs) || empty($priceConfigs)) {
                $priceConfigs = $this->generateDefaultPriceConfigs($cleanDynamicOptions, $payload);
                // Ensure all critical option variations (like SPOT UV SIDES, Colorspec, Radius) are covered
                $priceConfigs = $this->expandMissingOptions($priceConfigs, $cleanDynamicOptions);
            }

            $helpers = new HelpersFunctions();
            $configsSaved = 0;

            // Load all active store shipping methods from system database
            $cachedSystemShippings = [];
            $shipQuery = \App\Models\Shipping::query();
            if ($category && $category->id) {
                $catShips = \App\Models\Shipping::where('category_id', $category->id)->orderBy('shipping_value')->get();
                $dbShips = $catShips->isNotEmpty() ? $catShips : \App\Models\Shipping::orderBy('shipping_value')->get();
            } else {
                $dbShips = \App\Models\Shipping::orderBy('shipping_value')->get();
            }

            foreach ($dbShips as $sm) {
                $cachedSystemShippings[] = [
                    'id' => $sm->id,
                    'shipping_id' => $sm->shipping_id ?? (string) $sm->id,
                    'shippingLabel' => $sm->shipping_label,
                    'shipping_label' => $sm->shipping_label,
                    'shippingValue' => (int) $sm->shipping_value,
                    'shipping_value' => (int) $sm->shipping_value,
                    'price' => (float) $sm->price,
                    'note' => $sm->note ?? null,
                    'runsize' => $sm->runsize ?? null,
                    'category_id' => $sm->category_id ?? ($category->id ?? null),
                    'category_name' => $sm->category_name ?? ($category->name ?? ''),
                ];
            }

            if (empty($cachedSystemShippings)) {
                $cachedSystemShippings = [
                    [
                        'id' => 5,
                        'shipping_id' => 'ship-pickup-from-store-01-11-2026-aBI4bP0N',
                        'shippingLabel' => 'Pickup from Store',
                        'shipping_label' => 'Pickup from Store',
                        'shippingValue' => 0,
                        'shipping_value' => 0,
                        'price' => 0.0,
                        'note' => null,
                        'runsize' => null,
                        'category_id' => $category->id ?? null,
                        'category_name' => $category->name ?? '',
                    ]
                ];
            }

            // Create product-level shippingRanges
            if ($product->shippingRanges()->count() === 0 && !empty($cachedSystemShippings)) {
                $product->shippingRanges()->create([
                    'min_quantity' => 1,
                    'max_quantity' => 100000,
                    'discount' => 0,
                    'shippings' => $cachedSystemShippings,
                ]);
            }

            if (is_array($priceConfigs) && !empty($priceConfigs)) {
                $batchConfigs = [];
                $batchOptionsRel = [];
                $batchShippings = [];
                $batchTurnarounds = [];
                $now = now()->toDateTimeString();

                $startId = (DB::table('price_configurations')->max('id') ?? 0) + 1;
                $currId = $startId;

                foreach ($priceConfigs as $configData) {
                    $rawOptions = $configData['options'] ?? [];
                    $flatOptions = is_array($rawOptions)
                        ? $this->sanitizePriceConfigOptions($rawOptions, $cleanDynamicOptions, $helpers)
                        : [];

                    $batchConfigs[] = [
                        'id' => $currId,
                        'product_id' => $product->id,
                        'runsize' => $configData['runsize'] ?? 100,
                        'price' => $configData['price'] ?? 0,
                        'discount' => $configData['discount'] ?? 0,
                        'options' => json_encode($flatOptions),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    foreach ($flatOptions as $key => $val) {
                        $batchOptionsRel[] = [
                            'price_configuration_id' => $currId,
                            'key' => (string) $key,
                            'value' => (string) $val,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $incomingShippings = $configData['shippings'] ?? [];
                    $hasOnlyPlaceholderShipping = empty($incomingShippings) || (
                        count($incomingShippings) === 1 &&
                        stripos($incomingShippings[0]['shippingLabel'] ?? '', 'Standard Ground Shipping') !== false
                    );

                    // Always ensure all store shipping methods are included
                    if ($hasOnlyPlaceholderShipping || count($incomingShippings) < count($cachedSystemShippings)) {
                        $shippings = $cachedSystemShippings;
                    } else {
                        $shippings = $incomingShippings;
                    }

                    foreach ($shippings as $s) {
                        $batchShippings[] = [
                            'price_configuration_id' => $currId,
                            'shippingLabel' => $s['shippingLabel'] ?? ($s['shipping_label'] ?? 'Standard Shipping'),
                            'shippingValue' => $s['shippingValue'] ?? ($s['shipping_value'] ?? 0),
                            'price' => $s['price'] ?? 0,
                            'note' => $s['note'] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $turnarounds = $configData['turnarounds'] ?? [];
                    if (empty($turnarounds)) {
                        $turnarounds = [
                            [
                                'turnaroundLabel' => 'Next Business Day',
                                'turnaroundValue' => 1,
                                'price' => 10,
                                'note' => null,
                            ],
                            [
                                'turnaroundLabel' => '2 Business Days',
                                'turnaroundValue' => 2,
                                'price' => 0,
                                'note' => null,
                            ],
                            [
                                'turnaroundLabel' => '4 Business Days',
                                'turnaroundValue' => 4,
                                'price' => 0,
                                'note' => null,
                            ],
                        ];
                    }
                    foreach ($turnarounds as $t) {
                        $tNote = $t['note'] ?? null;
                        if ($tNote && preg_match('/\+\d+%/', $tNote)) {
                            $tNote = null;
                        }
                        $batchTurnarounds[] = [
                            'price_configuration_id' => $currId,
                            'turnaroundLabel' => $t['turnaroundLabel'] ?? 'Standard Turnaround',
                            'turnaroundValue' => $t['turnaroundValue'] ?? 4,
                            'price' => $t['price'] ?? 0,
                            'note' => $tNote,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $currId++;
                    $configsSaved++;
                }

                foreach (array_chunk($batchConfigs, 500) as $chunk) {
                    DB::table('price_configurations')->insert($chunk);
                }
                foreach (array_chunk($batchOptionsRel, 1000) as $chunk) {
                    DB::table('price_configuration_options')->insert($chunk);
                }
                foreach (array_chunk($batchShippings, 1000) as $chunk) {
                    DB::table('price_configuration_shippings')->insert($chunk);
                }
                foreach (array_chunk($batchTurnarounds, 1000) as $chunk) {
                    DB::table('price_configuration_turnarounds')->insert($chunk);
                }
            }

            DB::commit();

            Log::info("4over product [{$productName}] imported directly. ID: {$product->id}, Configs: {$configsSaved}");

            return response()->json([
                'success' => true,
                'isError' => false,
                'message' => "Product '{$productName}' imported successfully into the database!",
                'data' => [
                    'id' => $product->id,
                    'productId' => $product->product_id,
                    'productName' => $product->product_name,
                    'category' => $category->name,
                    'categoryId' => $category->id,
                    'imagesCount' => count($images),
                    'faqsCount' => count($faqs),
                    'priceConfigurationsCount' => $configsSaved,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('4over product import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'isError' => true,
                'message' => 'Failed to import 4over product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean dynamicOptions by structuring them into a proper conditional hierarchy:
     * - 'SPOT UV SIDES' is nested conditionally inside 'COATING':
     *     - Non-Spot UV coatings (Matte, No Coating, etc.) have ONLY 1 item: 'No Spot UV'.
     *     - Spot UV coating has the Spot UV choices ('Spot UV Both Sides', 'Spot UV Front', 'Spot UV Back - Full UV on the Front').
     * - 'Radius of Corners' is nested conditionally inside 'SHAPE':
     *     - Rounded shapes have the radius choices ('Rounded 1/8"', 'Rounded 1/4"').
     *     - Non-rounded shapes (Rectangle, etc.) have empty options [].
     * - Removes 'SPOT UV SIDES' and 'Radius of Corners' from top-level root groups.
     */
    /**
     * Clean dynamicOptions into a complete hierarchical tree:
     * Parent (Size) -> Child (Shape) -> Child of Shape (Radius if rounded, else Stock)
     * -> Stock -> Colorspec -> Coating -> (SPOT UV SIDES if Spot UV) -> Lamination (tail options)
     */
    protected function cleanDynamicOptions(array $options): array
    {
        $rawGroups = [];
        $extractGroupsRecursively = function ($nodes) use (&$extractGroupsRecursively, &$rawGroups) {
            foreach ($nodes as $opt) {
                $name = trim($opt['optionName'] ?? '');
                if (!$name || $name === 'Choose an option below' || $name === 'Turnaround Time') continue;

                if (!isset($rawGroups[$name])) {
                    $rawGroups[$name] = [];
                }

                foreach ($opt['options'] ?? [] as $sub) {
                    $subName = trim($sub['optionName'] ?? '');
                    if ($subName && $subName !== 'Choose an option below') {
                        $existingNames = array_column($rawGroups[$name], 'optionName');
                        if (!in_array($subName, $existingNames, true)) {
                            $rawGroups[$name][] = [
                                'optionName' => $subName,
                                'options' => [],
                            ];
                        }
                    }

                    if (!empty($sub['options']) && is_array($sub['options'])) {
                        $extractGroupsRecursively($sub['options']);
                    }
                }
            }
        };

        $extractGroupsRecursively($options);

        return $this->buildHierarchicalDynamicOptions($rawGroups);
    }

    /**
     * Helper to construct a clean conditional hierarchy:
     * - 'Radius of Corners' is nested conditionally inside 'Shape' (for Rounded 2 Corners / Rounded 4 Corners).
     * - 'SPOT UV SIDES' is nested conditionally inside 'Coating' (for Spot UV).
     * - Top-level root options: Size, Shape, Stock, Colorspec, Coating, Lamination (and any other attributes).
     * - 'Radius of Corners' and 'SPOT UV SIDES' are REMOVED from the root level.
     */
    protected function buildHierarchicalDynamicOptions(array $rawGroups): array
    {
        if (empty($rawGroups)) {
            return [];
        }

        $findKey = function(string $target) use ($rawGroups) {
            foreach (array_keys($rawGroups) as $k) {
                if (strcasecmp($k, $target) === 0) return $k;
            }
            return null;
        };

        $shapeKey = $findKey('Shape');
        $radiusKey = $findKey('Radius of Corners') ?: $findKey('Radius');
        if (!$radiusKey) {
            foreach (array_keys($rawGroups) as $k) {
                if (stripos($k, 'radius') !== false && stripos($k, 'corner') !== false) {
                    $radiusKey = $k;
                    break;
                }
            }
        }

        $coatingKey = $findKey('Coating');
        $spotUvSidesKey = $findKey('SPOT UV SIDES') ?: $findKey('SPOT UV');

        $radiusChoices = [];
        if ($radiusKey && !empty($rawGroups[$radiusKey])) {
            foreach ($rawGroups[$radiusKey] as $rc) {
                $val = trim($rc['optionName'] ?? (is_string($rc) ? $rc : ''));
                if ($val && $val !== 'Choose an option below') {
                    $radiusChoices[] = $val;
                }
            }
        }

        $spotUvChoices = [];
        if ($spotUvSidesKey && !empty($rawGroups[$spotUvSidesKey])) {
            foreach ($rawGroups[$spotUvSidesKey] as $sc) {
                $val = trim($sc['optionName'] ?? (is_string($sc) ? $sc : ''));
                if ($val && $val !== 'Choose an option below') {
                    $spotUvChoices[] = $val;
                }
            }
            if (in_array('No Spot UV', $spotUvChoices, true) && count($spotUvChoices) > 1) {
                $hasSpotUvCoating = false;
                if ($coatingKey && !empty($rawGroups[$coatingKey])) {
                    foreach ($rawGroups[$coatingKey] as $cg) {
                        $cName = trim(is_string($cg) ? $cg : ($cg['optionName'] ?? ''));
                        if (strcasecmp($cName, 'Spot UV') === 0) {
                            $hasSpotUvCoating = true;
                            break;
                        }
                    }
                }
                if (!$hasSpotUvCoating) {
                    $spotUvChoices = ['No Spot UV'];
                }
            }
        }

        $desiredOrder = [
            'size',
            'shape',
            'radius',
            'stock',
            'substrate',
            'paper',
            'colorspec',
            'coating',
            'spot uv',
            'lamination',
        ];

        uksort($rawGroups, function($a, $b) use ($desiredOrder) {
            $indexA = 99;
            $indexB = 99;
            foreach ($desiredOrder as $idx => $term) {
                if ($indexA === 99 && stripos($a, $term) !== false) $indexA = $idx;
                if ($indexB === 99 && stripos($b, $term) !== false) $indexB = $idx;
            }
            return $indexA - $indexB;
        });

        $result = [];

        foreach ($rawGroups as $groupName => $choices) {
            // Remove Radius of Corners and SPOT UV SIDES from root level
            if ($radiusKey && strcasecmp($groupName, $radiusKey) === 0) continue;
            if ($spotUvSidesKey && strcasecmp($groupName, $spotUvSidesKey) === 0) continue;

            // Conditional Shape -> Radius of Corners
            if ($shapeKey && strcasecmp($groupName, $shapeKey) === 0) {
                $shapedOpts = [];
                foreach ($choices as $sh) {
                    $shName = trim($sh['optionName'] ?? (is_string($sh) ? $sh : ''));
                    if (!$shName || $shName === 'Choose an option below') continue;

                    $isRounded = (stripos($shName, 'Round') !== false && stripos($shName, 'Rectangle') === false && strcasecmp($shName, 'Round') !== 0);
                    if ($isRounded && !empty($radiusChoices)) {
                        $radOpts = [];
                        foreach ($radiusChoices as $rad) {
                            $radOpts[] = ['optionName' => trim($rad), 'options' => []];
                        }
                        $shapedOpts[] = [
                            'optionName' => $shName,
                            'options' => [
                                [
                                    'optionName' => $radiusKey ?: 'Radius of Corners',
                                    'options' => $radOpts,
                                ]
                            ]
                        ];
                    } else {
                        $shapedOpts[] = [
                            'optionName' => $shName,
                            'options' => [],
                        ];
                    }
                }
                $result[] = [
                    'optionName' => $groupName,
                    'options' => $shapedOpts,
                ];
                continue;
            }

            // Conditional Coating -> SPOT UV SIDES (for both Spot UV and Matte)
            if ($coatingKey && strcasecmp($groupName, $coatingKey) === 0) {
                $coatingOpts = [];
                foreach ($choices as $co) {
                    $coName = trim($co['optionName'] ?? (is_string($co) ? $co : ''));
                    if (!$coName || $coName === 'Choose an option below') continue;

                    $hasSpotUv = (stripos($coName, 'Spot UV') !== false || stripos($coName, 'Matte') !== false);
                    if ($hasSpotUv && !empty($spotUvChoices)) {
                        $suvOpts = [];
                        foreach ($spotUvChoices as $suv) {
                            $suvOpts[] = ['optionName' => trim($suv), 'options' => []];
                        }
                        $coatingOpts[] = [
                            'optionName' => $coName,
                            'options' => [
                                [
                                    'optionName' => $spotUvSidesKey ?: 'SPOT UV SIDES',
                                    'options' => $suvOpts,
                                ]
                            ]
                        ];
                    } else {
                        $coatingOpts[] = [
                            'optionName' => $coName,
                            'options' => [],
                        ];
                    }
                }
                $result[] = [
                    'optionName' => $groupName,
                    'options' => $coatingOpts,
                ];
                continue;
            }

            // Other groups (Size, Stock, Colorspec, Lamination, etc.)
            $opts = [];
            foreach ($choices as $c) {
                $cName = trim($c['optionName'] ?? (is_string($c) ? $c : ''));
                if ($cName && $cName !== 'Choose an option below') {
                    $opts[] = ['optionName' => $cName, 'options' => []];
                }
            }
            if (!empty($opts)) {
                $result[] = [
                    'optionName' => $groupName,
                    'options' => $opts,
                ];
            }
        }

        return $result;
    }

    /**
     * Merge all extracted 4over option groups and choices into Category variants.
     */
    protected function mergeCategoryVariants(Category $category, array $dynamicOptions): void
    {
        $allGroups = [];
        $extractAll = function ($nodes) use (&$extractAll, &$allGroups) {
            foreach ($nodes as $node) {
                $gName = trim($node['optionName'] ?? '');
                if (!$gName || $gName === 'Turnaround Time' || $gName === 'Choose an option below') continue;
                if (!isset($allGroups[$gName])) {
                    $allGroups[$gName] = [];
                }
                foreach ($node['options'] ?? [] as $sub) {
                    $cName = trim($sub['optionName'] ?? '');
                    if ($cName && $cName !== 'Choose an option below' && !in_array($cName, $allGroups[$gName], true)) {
                        $allGroups[$gName][] = $cName;
                    }
                    if (!empty($sub['options']) && is_array($sub['options'])) {
                        $extractAll($sub['options']);
                    }
                }
            }
        };
        $extractAll($dynamicOptions);

        $catVariants = is_array($category->variants) ? $category->variants : [];
        $changed = false;

        foreach ($allGroups as $groupName => $choices) {
            $matchedKey = null;
            foreach (array_keys($catVariants) as $k) {
                if (strcasecmp($k, $groupName) === 0) {
                    $matchedKey = $k;
                    break;
                }
            }
            $keyToUse = $matchedKey ?: $groupName;
            if (!isset($catVariants[$keyToUse])) {
                $catVariants[$keyToUse] = ['label' => $groupName, 'options' => []];
                $changed = true;
            }
            $existing = $catVariants[$keyToUse]['options'] ?? [];
            foreach ($choices as $optVal) {
                if (!in_array($optVal, $existing, true)) {
                    $existing[] = $optVal;
                    $changed = true;
                }
            }
            $catVariants[$keyToUse]['options'] = $existing;
        }

        if ($changed) {
            $category->variants = $catVariants;
            $category->save();
            Log::info("Category ID {$category->id} ({$category->name}) variants updated with 4over product specs.");
        }
    }

    /**
     * Generate fallback price configurations across clean dynamicOptions if payload has none.
     */
    protected function generateDefaultPriceConfigs(array $cleanDynamicOptions, array $payload): array
    {
        $standardRunsizes = [
            100 => 25.00,
            250 => 35.00,
            500 => 45.00,
            1000 => 60.00,
            2500 => 110.00,
            5000 => 195.00,
        ];

        $turnarounds = $payload['turnarounds'] ?? ($payload['productOptions']['turnarounds'] ?? []);
        if (empty($turnarounds)) {
            $turnarounds = [
                [
                    'turnaroundLabel' => 'Standard Turnaround (2 - 4 Business Days)',
                    'turnaroundValue' => 4,
                    'price' => 0,
                    'note' => 'Standard Turnaround',
                ]
            ];
        }

        $dbShips = \App\Models\Shipping::orderBy('shipping_value')->get();
        $shippings = [];
        foreach ($dbShips as $sm) {
            $shippings[] = [
                'id' => $sm->id,
                'shipping_id' => $sm->shipping_id ?? (string) $sm->id,
                'shippingLabel' => $sm->shipping_label,
                'shipping_label' => $sm->shipping_label,
                'shippingValue' => (int) $sm->shipping_value,
                'shipping_value' => (int) $sm->shipping_value,
                'price' => (float) $sm->price,
                'note' => $sm->note ?? null,
            ];
        }
        if (empty($shippings)) {
            $shippings = [
                [
                    'id' => 5,
                    'shipping_id' => 'ship-pickup-from-store-01-11-2026-aBI4bP0N',
                    'shippingLabel' => 'Pickup from Store',
                    'shipping_label' => 'Pickup from Store',
                    'shippingValue' => 0,
                    'shipping_value' => 0,
                    'price' => 0,
                    'note' => null,
                ]
            ];
        }

        $baseConfigs = [];
        foreach ($standardRunsizes as $rs => $pr) {
            $baseConfigs[] = [
                'runsize' => $rs,
                'price' => number_format($pr, 4, '.', ''),
                'discount' => 0,
                'options' => [],
                'turnarounds' => $turnarounds,
                'shippings' => $shippings,
            ];
        }

        return $this->expandMissingOptions($baseConfigs, $cleanDynamicOptions);
    }

    /**
     * Sanitize price configuration options so they match the product's dynamicOptions 100%.
     * Automatically normalizes flat options into nested conditional keys (e.g. Coating.Matte.SPOT UV SIDES)
     * and strips non-applicable child options (e.g. Radius on Rectangle).
     */
    protected function sanitizePriceConfigOptions(array $rawOptions, array $dynamicOptions, HelpersFunctions $helpers): array
    {
        $flat = $helpers->flattenSelectedOptions($rawOptions);
        if (empty($flat) && !empty($rawOptions)) {
            foreach ($rawOptions as $k => $v) {
                if (is_string($v) || is_numeric($v)) {
                    $flat[$k] = (string) $v;
                } elseif (is_array($v) && isset($v['selected'])) {
                    $flat[$k] = (string) $v['selected'];
                }
            }
        }

        $validRootNames = [];
        foreach ($dynamicOptions as $opt) {
            $rootName = $opt['optionName'] ?? null;
            if ($rootName) $validRootNames[] = $rootName;
        }

        $sanitized = [];
        foreach ($flat as $key => $val) {
            $strKey = (string)$key;
            if ($strKey === 'Turnaround Time') continue;

            // Extract leaf name if dotted
            $leafKey = (strrpos($strKey, '.') !== false) ? substr($strKey, strrpos($strKey, '.') + 1) : $strKey;

            // Match case-insensitively with valid root names
            $matchedKey = $leafKey;
            foreach ($validRootNames as $vr) {
                if (strcasecmp($vr, $leafKey) === 0) {
                    $matchedKey = $vr;
                    break;
                }
            }

            $sanitized[$matchedKey] = (string)$val;
        }

        return $sanitized;
    }

    /**
     * Expand price configurations across conditional options (Coating x SPOT UV, Shape x Radius, Colorspec, etc.)
     * so that changing any option on the storefront always finds a matching price configuration.
     */
    protected function expandMissingOptions(array $priceConfigs, array $cleanDynamicOptions): array
    {
        if (empty($priceConfigs)) {
            return [];
        }

        // Map root and nested choices
        $rootOptions = [];
        $extractAll = function ($nodes) use (&$extractAll, &$rootOptions) {
            foreach ($nodes as $node) {
                $gName = trim($node['optionName'] ?? '');
                if (!$gName || $gName === 'Turnaround Time' || $gName === 'Choose an option below') continue;
                if (!isset($rootOptions[$gName])) {
                    $rootOptions[$gName] = [];
                }
                foreach ($node['options'] ?? [] as $sub) {
                    $cName = trim($sub['optionName'] ?? '');
                    if ($cName && $cName !== 'Choose an option below' && !in_array($cName, $rootOptions[$gName], true)) {
                        $rootOptions[$gName][] = $cName;
                    }
                    if (!empty($sub['options']) && is_array($sub['options'])) {
                        $extractAll($sub['options']);
                    }
                }
            }
        };
        $extractAll($cleanDynamicOptions);

        if (empty($rootOptions)) {
            return $priceConfigs;
        }

        // Find key dimension names case-insensitively
        $findKey = function(array $arr, string $target) {
            foreach (array_keys($arr) as $k) {
                if (strcasecmp($k, $target) === 0) return $k;
            }
            return null;
        };

        $coatingKey = $findKey($rootOptions, 'Coating');
        $shapeKey = $findKey($rootOptions, 'Shape');
        $colorspecKey = $findKey($rootOptions, 'Colorspec');
        $stockKey = $findKey($rootOptions, 'Stock') ?: $findKey($rootOptions, 'Paper Stock');
        $sizeKey = $findKey($rootOptions, 'Size');

        $coatings = $coatingKey ? $rootOptions[$coatingKey] : ['Matte'];
        $shapes = $shapeKey ? $rootOptions[$shapeKey] : ['Rectangle'];
        $colorspecs = $colorspecKey ? $rootOptions[$colorspecKey] : [];
        $stocks = $stockKey ? $rootOptions[$stockKey] : [];
        $sizes = $sizeKey ? $rootOptions[$sizeKey] : [];

        $radKey = $findKey($rootOptions, 'Radius of Corners') ?: $findKey($rootOptions, 'Radius');
        $radii = $radKey ? $rootOptions[$radKey] : [];

        $spotUvKey = $findKey($rootOptions, 'SPOT UV SIDES') ?: $findKey($rootOptions, 'SPOT UV');
        $uvs = $spotUvKey ? $rootOptions[$spotUvKey] : [];

        // Build default template across root options (excluding conditional children)
        $defaultTemplate = [];
        foreach ($rootOptions as $k => $vals) {
            if ($radKey && $k === $radKey) continue;
            if ($spotUvKey && $k === $spotUvKey) continue;
            $defaultTemplate[$k] = $vals[0];
        }

        // Build Shape combos (Rectangle has NO radius, Rounded has radius)
        $shapeCombos = [];
        foreach ($shapes as $sh) {
            $isRounded = (stripos($sh, 'Round') !== false && stripos($sh, 'Rectangle') === false && strcasecmp($sh, 'Round') !== 0);
            if ($isRounded && $radKey && !empty($radii)) {
                foreach ($radii as $rad) {
                    $shapeCombos[] = [
                        $shapeKey => $sh,
                        $radKey => $rad,
                    ];
                }
            } else {
                $shapeCombos[] = [
                    $shapeKey => $sh,
                ];
            }
        }

        // Build Coating combos (Non-UV coatings have NO Spot UV; Spot UV / Matte have Spot UV)
        $coatingCombos = [];
        foreach ($coatings as $co) {
            $hasSpotUv = (stripos($co, 'Spot UV') !== false || stripos($co, 'Matte') !== false);
            if ($hasSpotUv && $spotUvKey && !empty($uvs)) {
                foreach ($uvs as $uv) {
                    $coatingCombos[] = [
                        $coatingKey => $co,
                        $spotUvKey => $uv,
                    ];
                }
            } else {
                $coatingCombos[] = [
                    $coatingKey => $co,
                ];
            }
        }

        // Combine: Shape x Coating x Colorspec x Stock x Size sensibly
        $allCombos = [];
        $csList = !empty($colorspecs) ? $colorspecs : [$defaultTemplate[$colorspecKey] ?? 'Standard'];

        foreach ($shapeCombos as $sc) {
            foreach ($coatingCombos as $cc) {
                foreach ($csList as $cs) {
                    $combo = $defaultTemplate;
                    if ($colorspecKey) $combo[$colorspecKey] = $cs;
                    foreach ($sc as $k => $v) $combo[$k] = $v;
                    foreach ($cc as $k => $v) $combo[$k] = $v;
                    $allCombos[] = $combo;
                }
            }
        }

        // Additional sizes if multiple
        if (count($sizes) > 1) {
            $baseShape = $shapeCombos[0] ?? [];
            $baseCoating = $coatingCombos[0] ?? [];
            foreach (array_slice($sizes, 1) as $sz) {
                $combo = $defaultTemplate;
                if ($sizeKey) $combo[$sizeKey] = $sz;
                foreach ($baseShape as $k => $v) $combo[$k] = $v;
                foreach ($baseCoating as $k => $v) $combo[$k] = $v;
                $allCombos[] = $combo;
            }
        }

        // Additional stocks if multiple
        if (count($stocks) > 1) {
            $baseShape = $shapeCombos[0] ?? [];
            $baseCoating = $coatingCombos[0] ?? [];
            foreach (array_slice($stocks, 1) as $st) {
                $combo = $defaultTemplate;
                if ($stockKey) $combo[$stockKey] = $st;
                foreach ($baseShape as $k => $v) $combo[$k] = $v;
                foreach ($baseCoating as $k => $v) $combo[$k] = $v;
                $allCombos[] = $combo;
            }
        }

        // Group incoming baseConfigs by runsize
        $runsizeConfigs = [];
        foreach ($priceConfigs as $cfg) {
            $rs = (int)($cfg['runsize'] ?? 100);
            if (!isset($runsizeConfigs[$rs])) {
                $runsizeConfigs[$rs] = $cfg;
            }
        }

        // Cap total generated configs at 3500 to stay optimal and prevent timeouts
        $maxPerRunsize = (int)floor(3500 / max(1, count($runsizeConfigs)));
        $combosToUse = array_slice($allCombos, 0, $maxPerRunsize);

        $expanded = [];
        foreach ($runsizeConfigs as $rs => $baseCfg) {
            foreach ($combosToUse as $combo) {
                $newCfg = $baseCfg;
                $newCfg['options'] = $combo;
                $expanded[] = $newCfg;
            }
        }

        return !empty($expanded) ? $expanded : $priceConfigs;
    }
}
