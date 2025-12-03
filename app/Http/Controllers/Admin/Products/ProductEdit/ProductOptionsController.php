<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductOptionsController extends Controller
{
    /**
     * GET /admin/products/{product}/options
     * Return dynamicOptions and extraDynamicOptions for the product.
     */
    public function getDynamicOptions(Product $product)
    {
        // Optionally you may authorize here (if not handled by middleware)
        // $this->authorize('update', $product);

        // Return the options as arrays (assume DB columns store JSON)
        return response()->json([
            'product_id' => $product->id,
            'dynamicOptions' => $product->dynamicOptions ?? null,
            'extraDynamicOptions' => $product->extraDynamicOptions ?? null,
            'updated_at' => optional($product->updated_at)->toIso8601String(),
        ], Response::HTTP_OK);
    }

    /**
     * PATCH /admin/products/{product}/options
     *
     * Body:
     * {
     *   "dynamicOptions": { ... },          // optional
     *   "extraDynamicOptions": { ... },     // optional
     *   "mode": "merge"                     // optional: "merge" (default) or "replace"
     * }
     *
     * Behavior:
     * - mode=merge : merge incoming arrays into existing (recursive); incoming keys overwrite existing.
     * - mode=replace : replace the stored value with incoming value (for that field).
     *
     * Also supports optimistic locking via 'updated_at' in payload or If-Unmodified-Since header.
     */
    public function updateDynamicOptions(Request $request, Product $product)
    {
        // Basic auth/permission check (if not already covered)
        // $this->authorize('update', $product);

        // optimistic locking (optional)
        $clientUpdatedAt = $request->header('If-Unmodified-Since') ?? $request->input('updated_at');
        if ($clientUpdatedAt) {
            $serverUpdatedAt = $product->updated_at ? $product->updated_at->toIso8601String() : null;
            if ($serverUpdatedAt !== null && $clientUpdatedAt !== $serverUpdatedAt) {
                return response()->json([
                    'message' => 'Conflict: product has been modified by someone else.',
                    'server_updated_at' => $serverUpdatedAt,
                ], Response::HTTP_CONFLICT);
            }
        }

        // validate incoming payload (partial)
        $validator = Validator::make($request->all(), [
            'dynamicOptions' => 'nullable|array',
            'extraDynamicOptions' => 'nullable|array',
            'mode' => 'nullable|in:merge,replace',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();

        // nothing to do?
        if (!isset($data['dynamicOptions']) && !isset($data['extraDynamicOptions'])) {
            return response()->json(['message' => 'No option payload provided.'], Response::HTTP_BAD_REQUEST);
        }

        $mode = $data['mode'] ?? 'merge';

        DB::beginTransaction();
        try {
            // load fresh product inside transaction
            $product->refresh();

            // handle dynamicOptions
            if (isset($data['dynamicOptions'])) {
                if ($mode === 'replace' || empty($product->dynamicOptions)) {
                    $product->dynamicOptions = $data['dynamicOptions'];
                } else {
                    // merge
                    $merged = $this->arrayMergeRecursiveDistinct($product->dynamicOptions, $data['dynamicOptions']);
                    $product->dynamicOptions = $merged;
                }
            }

            // handle extraDynamicOptions
            if (isset($data['extraDynamicOptions'])) {
                if ($mode === 'replace' || empty($product->extraDynamicOptions)) {
                    $product->extraDynamicOptions = $data['extraDynamicOptions'];
                } else {
                    $merged = $this->arrayMergeRecursiveDistinct($product->extraDynamicOptions, $data['extraDynamicOptions']);
                    $product->extraDynamicOptions = $merged;
                }
            }

            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Product options updated successfully.',
                'product_id' => $product->id,
                'dynamicOptions' => $product->dynamicOptions,
                'extraDynamicOptions' => $product->extraDynamicOptions,
                'updated_at' => optional($product->updated_at)->toIso8601String(),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('updateDynamicOptions error: '.$e->getMessage());
            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deep-merge two associative arrays where incoming overwrites existing.
     * Returns merged array.
     */
    protected function arrayMergeRecursiveDistinct($array1, $array2)
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
}
