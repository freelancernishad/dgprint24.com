<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Jobs\UploadSingleBulkImageJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\TurnAroundTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminBulkImportController extends Controller
{
    /**
     * Bulk import categories, subcategories (with variants), and products (with dynamicOptions) from JSON.
     */
    public function import(Request $request)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $validator = Validator::make($request->all(), [
            'skipExisting' => 'nullable|boolean',
            'generateCombinations' => 'nullable|boolean',
            'runSizes' => 'nullable|array',
            'runSizes.*' => 'integer|min:1',
            'categories' => 'required|array|min:1',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.categoryDescription' => 'nullable|string',
            'categories.*.categoryImage' => 'nullable|string',
            'categories.*.active' => 'nullable|boolean',
            'categories.*.show_in_navbar' => 'nullable|boolean',
            'categories.*.tags' => 'nullable|array',
            'categories.*.variants' => 'nullable|array',
            'categories.*.subcategories' => 'nullable|array',
            'categories.*.subcategories.*.name' => 'required|string|max:255',
            'categories.*.subcategories.*.categoryDescription' => 'nullable|string',
            'categories.*.subcategories.*.active' => 'nullable|boolean',
            'categories.*.subcategories.*.tags' => 'nullable|array',
            'categories.*.subcategories.*.variants' => 'nullable|array',
            'categories.*.subcategories.*.products' => 'nullable|array',
            'categories.*.products' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $skipExisting = (bool) ($data['skipExisting'] ?? false);
        $generateCombinations = (bool) ($data['generateCombinations'] ?? false);
        $runSizes = !empty($data['runSizes']) ? array_values(array_unique(array_map('intval', $data['runSizes']))) : [100, 250, 500, 1000, 2500, 5000];

        $storeShippings = $generateCombinations ? Shipping::all()->map(fn ($s) => [
            'shippingLabel' => $s->shipping_label ?? 'Shipping',
            'shippingValue' => (int) ($s->shipping_value ?? 0),
            'price' => (float) ($s->price ?? 0),
            'note' => $s->note ?? null,
        ])->all() : [];

        $storeTurnarounds = $generateCombinations ? TurnAroundTime::all()->map(fn ($t) => [
            'turnaroundLabel' => $t->turnaround_label ?? 'Turnaround',
            'turnaroundValue' => (int) ($t->turnaround_value ?? 0),
            'price' => (float) ($t->price ?? 0),
            'discount' => $t->discount !== null ? (float) $t->discount : null,
            'note' => $t->note ?? null,
        ])->all() : [];

        $summary = [
            'categories_created' => 0,
            'categories_updated' => 0,
            'categories_skipped' => 0,
            'subcategories_created' => 0,
            'subcategories_updated' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_skipped' => 0,
            'combinations_created' => 0,
            'images_queued' => 0,
        ];

        $categoryImageTasks = [];
        $productImageTasks = [];

        DB::beginTransaction();
        try {
            foreach ($data['categories'] as $catIndex => $catData) {
                $parentCategory = $this->upsertCategory($catData, null, $catIndex + 1, $summary, false, $categoryImageTasks, $skipExisting);

                if (!empty($catData['products']) && is_array($catData['products'])) {
                    foreach ($catData['products'] as $prodIndex => $prodData) {
                        $product = $this->upsertProduct($prodData, $parentCategory->id, $prodIndex + 1, $summary, $productImageTasks, $skipExisting);
                        if ($generateCombinations) {
                            $this->generateDefaultCombinations($product, $runSizes, $storeShippings, $storeTurnarounds, $summary);
                        }
                    }
                }

                $mergedParentVariants = $catData['variants'] ?? [];

                if (!empty($catData['subcategories']) && is_array($catData['subcategories'])) {
                    foreach ($catData['subcategories'] as $subIndex => $subData) {
                        $subCategory = $this->upsertCategory($subData, $parentCategory->id, $subIndex + 1, $summary, true, $categoryImageTasks, $skipExisting);
                        $mergedParentVariants = $this->mergeVariants($mergedParentVariants, $subData['variants'] ?? []);

                        if (!empty($subData['products']) && is_array($subData['products'])) {
                            foreach ($subData['products'] as $prodIndex => $prodData) {
                                $product = $this->upsertProduct($prodData, $subCategory->id, $prodIndex + 1, $summary, $productImageTasks, $skipExisting);
                                if ($generateCombinations) {
                                    $this->generateDefaultCombinations($product, $runSizes, $storeShippings, $storeTurnarounds, $summary);
                                }
                            }
                        }
                    }
                }

                if (!empty($mergedParentVariants)) {
                    $finalVariants = $skipExisting
                        ? $this->mergeVariants($parentCategory->variants ?? [], $mergedParentVariants)
                        : $mergedParentVariants;

                    $parentCategory->update([
                        'variants' => $this->normalizeInches($finalVariants),
                    ]);
                }
            }

            DB::commit();

            $totalImages = count($categoryImageTasks);
            foreach ($productImageTasks as $task) {
                $totalImages += count($task['sources']);
            }

            $currentImage = 0;
            foreach ($categoryImageTasks as $task) {
                $currentImage++;
                UploadSingleBulkImageJob::dispatch(
                    'category',
                    (int) $task['category_id'],
                    (string) $task['source'],
                    0,
                    $currentImage,
                    $totalImages
                );
            }

            foreach ($productImageTasks as $task) {
                foreach (array_values($task['sources']) as $index => $imgSource) {
                    $currentImage++;
                    UploadSingleBulkImageJob::dispatch(
                        'product',
                        (int) $task['product_id'],
                        (string) $imgSource,
                        $index,
                        $currentImage,
                        $totalImages
                    );
                }
            }

            return response()->json([
                'message' => 'Bulk import completed successfully.',
                'summary' => $summary,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Bulk import failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function upsertCategory(array $data, ?int $parentId, int $defaultSerial, array &$summary, bool $isSubcategory, array &$categoryImageTasks, bool $skipExisting = false): Category
    {
        $name = trim($data['name']);
        $variants = $this->normalizeInches($data['variants'] ?? []);

        $existing = $this->findMatchingCategory($name);

        if ($existing && $skipExisting) {
            if (!empty($variants)) {
                $existing->update([
                    'variants' => $this->mergeVariants($existing->variants ?? [], $variants),
                ]);
            }
            if (!empty($data['categoryImage']) && empty($existing->category_image)) {
                $categoryImageTasks[] = [
                    'category_id' => $existing->id,
                    'source' => (string) $data['categoryImage'],
                ];
                $summary['images_queued']++;
            }
            $summary['categories_skipped']++;
            return $existing;
        }

        $payload = [
            'name' => $name,
            'parent_id' => $parentId,
            'category_description' => $data['categoryDescription'] ?? ($existing->category_description ?? null),
            'variants' => !empty($variants) ? $variants : ($existing->variants ?? []),
            'tags' => $data['tags'] ?? ($existing->tags ?? []),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : ($existing->active ?? true),
            'show_in_navbar' => array_key_exists('show_in_navbar', $data) ? (bool) $data['show_in_navbar'] : ($existing->show_in_navbar ?? false),
            'serial' => $data['serial'] ?? ($existing->serial ?? $defaultSerial),
        ];

        if ($existing) {
            $existing->update($payload);
            $summary[$isSubcategory ? 'subcategories_updated' : 'categories_updated']++;
            $category = $existing;
        } else {
            $category = Category::create($payload);
            $summary[$isSubcategory ? 'subcategories_created' : 'categories_created']++;
        }

        if (!empty($data['categoryImage']) && empty($category->category_image)) {
            $categoryImageTasks[] = [
                'category_id' => $category->id,
                'source' => (string) $data['categoryImage'],
            ];
            $summary['images_queued']++;
        }

        return $category;
    }

    private function upsertProduct(array $data, int $categoryId, int $defaultSerial, array &$summary, array &$productImageTasks, bool $skipExisting = false): Product
    {
        $productName = trim($data['productName'] ?? $data['product_name'] ?? '');
        if ($productName === '') {
            throw new \InvalidArgumentException('Product name is required for every product.');
        }

        $existing = $this->findMatchingProduct($categoryId, $productName);

        if ($existing && $skipExisting) {
            $summary['products_skipped']++;
            return $existing;
        }

        $dynamicOptions = $this->normalizeInches($data['dynamicOptions'] ?? []);
        $extraDynamicOptions = $this->normalizeInches($data['extraDynamicOptions'] ?? []);

        $maxSerial = (int) Product::where('category_id', $categoryId)->max('serial');

        $payload = [
            'category_id' => $categoryId,
            'product_name' => $productName,
            'product_type' => $data['productType'] ?? ($existing->product_type ?? 'general'),
            'product_description' => $data['productDescription'] ?? ($existing->product_description ?? null),
            'dynamicOptions' => !empty($dynamicOptions) ? $dynamicOptions : ($existing->dynamicOptions ?? []),
            'extraDynamicOptions' => !empty($extraDynamicOptions) ? $extraDynamicOptions : ($existing->extraDynamicOptions ?? null),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : ($existing->active ?? true),
            'serial' => $data['serial'] ?? ($existing->serial ?? ($maxSerial + 1 ?: $defaultSerial)),
            'job_sample_price' => $data['jobSamplePrice'] ?? ($existing->job_sample_price ?? 0),
            'digital_proof_price' => $data['digitalProofPrice'] ?? ($existing->digital_proof_price ?? 0),
        ];

        if ($existing) {
            $existing->update($payload);
            $summary['products_updated']++;
            $product = $existing;
        } else {
            $payload['product_id'] = 'product-' . Str::uuid();
            $product = Product::create($payload);
            $summary['products_created']++;
        }

        if (!empty($data['productImages']) && is_array($data['productImages']) && $product->images()->count() === 0) {
            $productImageTasks[] = [
                'product_id' => $product->id,
                'sources' => array_values($data['productImages']),
            ];
            $summary['images_queued'] += count($data['productImages']);
        }

        return $product;
    }

    private function generateDefaultCombinations(Product $product, array $runSizes, array $storeShippings, array $storeTurnarounds, array &$summary): void
    {
        if ($product->product_type === 'banner' || $product->priceConfigurations()->count() > 0) {
            return;
        }

        $dynamicOptions = $product->dynamicOptions ?? [];
        if (empty($dynamicOptions) || !is_array($dynamicOptions)) {
            return;
        }

        $optionCombos = $this->buildOptionCombinations($dynamicOptions);
        if (empty($optionCombos)) {
            return;
        }

        foreach ($runSizes as $runsize) {
            foreach ($optionCombos as $flatOptions) {
                $pc = $product->priceConfigurations()->create([
                    'runsize' => $runsize,
                    'price' => 0,
                    'discount' => 0,
                    'options' => $flatOptions,
                ]);

                $pairs = [];
                foreach ($flatOptions as $k => $v) {
                    $pairs[] = ['key' => (string) $k, 'value' => (string) $v];
                }
                if (!empty($pairs)) {
                    $pc->optionsRel()->createMany($pairs);
                }

                if (!empty($storeShippings)) {
                    $pc->shippings()->createMany($storeShippings);
                }

                if (!empty($storeTurnarounds)) {
                    $pc->turnarounds()->createMany($storeTurnarounds);
                }

                $summary['combinations_created']++;
            }
        }
    }

    private function buildOptionCombinations(array $dynamicOptions): array
    {
        $combos = [[]];

        foreach ($dynamicOptions as $group) {
            $expanded = $this->expandOptionGroup($group);
            if (empty($expanded)) {
                continue;
            }
            $next = [];
            foreach ($combos as $base) {
                foreach ($expanded as $add) {
                    $next[] = array_merge($base, $add);
                }
            }
            $combos = $next;
        }

        return $combos;
    }

    private function expandOptionGroup(array $group): array
    {
        $groupLabel = trim((string) ($group['optionName'] ?? ''));
        $values = $group['options'] ?? [];
        if ($groupLabel === '' || empty($values) || !is_array($values)) {
            return [];
        }

        $results = [];
        foreach ($values as $valNode) {
            $valLabel = trim((string) ($valNode['optionName'] ?? ''));
            if ($valLabel === '') {
                continue;
            }

            $nestedGroups = $valNode['options'] ?? [];
            if (empty($nestedGroups) || !is_array($nestedGroups)) {
                $results[] = [$groupLabel => $valLabel];
            } else {
                $subCombos = [[$groupLabel => $valLabel]];
                foreach ($nestedGroups as $subGroup) {
                    $subExpanded = $this->expandOptionGroup($subGroup);
                    if (empty($subExpanded)) {
                        continue;
                    }
                    $next = [];
                    foreach ($subCombos as $base) {
                        foreach ($subExpanded as $add) {
                            $next[] = array_merge($base, $add);
                        }
                    }
                    $subCombos = $next;
                }
                foreach ($subCombos as $subObj) {
                    $results[] = $subObj;
                }
            }
        }

        return $results;
    }

    private function findMatchingCategory(string $name): ?Category
    {
        $exact = Category::whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($name))])->first();
        if ($exact) {
            return $exact;
        }

        $targetNorm = $this->normalizeNameForMatch($name);
        $targetSingular = rtrim($targetNorm, 's');

        foreach (Category::all() as $cat) {
            $catNorm = $this->normalizeNameForMatch((string) $cat->name);
            if ($catNorm === $targetNorm || rtrim($catNorm, 's') === $targetSingular) {
                return $cat;
            }
        }

        return null;
    }

    private function findMatchingProduct(int $categoryId, string $productName): ?Product
    {
        $exact = Product::where('category_id', $categoryId)
            ->whereRaw('LOWER(TRIM(product_name)) = ?', [Str::lower(trim($productName))])
            ->first();
        if ($exact) {
            return $exact;
        }

        $targetNorm = $this->normalizeNameForMatch($productName);
        $targetSingular = rtrim($targetNorm, 's');

        foreach (Product::where('category_id', $categoryId)->get() as $prod) {
            $prodNorm = $this->normalizeNameForMatch((string) $prod->product_name);
            if ($prodNorm === $targetNorm || rtrim($prodNorm, 's') === $targetSingular) {
                return $prod;
            }
        }

        return null;
    }

    private function normalizeNameForMatch(string $name): string
    {
        $s = Str::lower(trim($name));
        $s = str_replace(['&', '-', '_', '/'], [' and ', ' ', ' ', ' '], $s);
        $s = preg_replace('/[^a-z0-9\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    private function mergeVariants(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $group) {
            if (!is_array($group)) {
                continue;
            }
            $label = $group['label'] ?? $key;
            $options = $group['options'] ?? [];

            if (!isset($base[$key])) {
                $base[$key] = [
                    'label' => $label,
                    'options' => array_values(array_unique($options)),
                ];
            } else {
                $existingOptions = $base[$key]['options'] ?? [];
                $base[$key]['options'] = array_values(array_unique(array_merge($existingOptions, $options)));
            }
        }

        return $base;
    }

    private function normalizeInches(array $items): array
    {
        array_walk_recursive($items, function (&$item) {
            if (is_string($item)) {
                $item = str_replace('"', '″', $item);
            }
        });

        return $items;
    }
}
