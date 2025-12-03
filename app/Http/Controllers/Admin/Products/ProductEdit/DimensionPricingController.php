<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\DimensionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DimensionPricingController extends Controller
{
    // GET /admin/products/{product}/dimension-pricing
    public function getDimensionPricing(Product $product)
    {
        $dimensionPricing = $product->dimensionPricing()->first();
        return response()->json(['dimensionPricing' => $dimensionPricing], Response::HTTP_OK);
    }

    // PATCH /admin/products/{product}/dimension-pricing
    public function patchDimensionPricing(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'minwidth' => 'nullable|numeric|min:0',
            'maxwidth' => 'nullable|numeric|min:0',
            'minheight' => 'nullable|numeric|min:0',
            'maxheight' => 'nullable|numeric|min:0',
            'basePricePerSqFt' => 'nullable|numeric|min:0',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        DB::beginTransaction();
        try {
            $data = $v->validated();
            $dp = $product->dimensionPricing;
            if (!$dp) {
                $dp = $product->dimensionPricing()->create($data);
            } else {
                $dp->update($data);
            }
            DB::commit();
            return response()->json(['message'=>'Dimension pricing updated','dimensionPricing'=>$dp->fresh()], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('patchDimensionPricing: '.$e->getMessage());
            return response()->json(['message'=>'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

