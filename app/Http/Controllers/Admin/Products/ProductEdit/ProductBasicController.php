<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Product;

class ProductBasicController extends Controller
{
    /**
     * PATCH /admin/products/{product}/basic
     * Only allowed to update the whitelisted basic product fields.
     * Any other incoming fields (except updated_at for optimistic locking) will be rejected.
     */
    public function updateBasic(Request $request, Product $product)
    {
        // Whitelisted fields that are allowed to change via this endpoint
        $allowed = [
            'product_name',
            'product_type',
            'product_description',
            'thumbnail',
            'base_price',
            'active',
            'popular_product',
            'job_sample_price',
            'digital_proof_price',
        ];

        // 1) Reject if request contains keys outside allowed (protect relations / other columns)
        $incomingKeys = array_keys($request->all());
        $disallowed = array_diff($incomingKeys, $allowed);
        if (!empty($disallowed)) {
            return response()->json([
                'message' => 'Payload contains disallowed fields. Only the basic product fields can be updated here.',
                'disallowed_fields' => array_values($disallowed)
            ], Response::HTTP_BAD_REQUEST);
        }

        // 2) Optimistic locking (optional): check If-Unmodified-Since header or updated_at in body
        $clientUpdatedAt = $request->header('If-Unmodified-Since') ?? $request->input('updated_at');
        if ($clientUpdatedAt) {
            $serverUpdatedAt = $product->updated_at ? $product->updated_at->toIso8601String() : null;
            if ($serverUpdatedAt !== null && $clientUpdatedAt !== $serverUpdatedAt) {
                return response()->json([
                    'message' => 'Conflict: product has been modified by someone else.',
                    'server_updated_at' => $serverUpdatedAt
                ], Response::HTTP_CONFLICT);
            }
        }

        // 3) Validate only allowed fields (partial validation)
        $rules = [
            'product_name' => 'nullable|string|max:255',
            'product_type' => 'nullable|string|max:150',
            'product_description' => 'nullable|string',
            'thumbnail' => 'nullable|url',
            'base_price' => 'nullable|numeric|min:0',
            'active' => 'nullable|boolean',
            'popular_product' => 'nullable|boolean',
            'job_sample_price' => 'nullable|numeric|min:0',
            'digital_proof_price' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();

        // remove updated_at from update data if present (we do not save it directly)
        unset($data['updated_at']);

        if (empty($data)) {
            return response()->json(['message' => 'No updatable fields provided.'], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            // Only update the whitelisted fillable attributes (no relations touched)
            $product->update($data);

            DB::commit();

            // Return the minimal product resource (no deep relations)
            $product->refresh();

            return response()->json([
                'message' => 'Product basic fields updated successfully.',
                'product' => $product->only([
                    'id',
                    'product_name',
                    'product_type',
                    'product_description',
                    'thumbnail',
                    'base_price',
                    'active',
                    'popular_product',
                    'job_sample_price',
                    'digital_proof_price',
                ])
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProductBasic update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
