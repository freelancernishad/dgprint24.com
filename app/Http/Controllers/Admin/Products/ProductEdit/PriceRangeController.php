<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductPriceRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PriceRangeController extends Controller
{
    // GET /admin/products/{product}/price-ranges
    public function getPriceRanges(Product $product)
    {
        $priceRanges = $product->priceRanges()->orderBy('min_quantity')->get();
        return response()->json(['priceRanges' => $priceRanges], Response::HTTP_OK);
    }

    // POST /admin/products/{product}/price-ranges
    public function addPriceRange(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'minQuantity' => 'required|integer|min:0',
            'maxQuantity' => 'nullable|integer|min:0',
            'pricePerSqFt' => 'required|numeric|min:0',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);


        $data = [
            'min_quantity' => $v->validated()['minQuantity'],
            'max_quantity' => $v->validated()['maxQuantity'] ?? null,
            'price_per_sq_ft' => $v->validated()['pricePerSqFt'],
        ];

        $range = $product->priceRanges()->create($data);
        return response()->json(['message'=>'Price range added','range'=>$range], Response::HTTP_CREATED);
    }

    // PUT /admin/products/{product}/price-ranges/sync
    public function syncPriceRanges(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'ranges' => 'required|array',
            'ranges.*.id' => 'nullable|integer|exists:product_price_ranges,id',
            'ranges.*.minQuantity' => 'required|integer|min:0',
            'ranges.*.maxQuantity' => 'nullable|integer|min:0',
            'ranges.*.pricePerSqFt' => 'required|numeric|min:0',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $ranges = $v->validated()['ranges'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($ranges)->pluck('id')->filter()->values()->all();
            $product->priceRanges()->whereNotIn('id', $incomingIds)->delete();

            foreach ($ranges as $r) {
                if (!empty($r['id'])) {
                    $product->priceRanges()->where('id', $r['id'])->update([
                        'min_quantity' => $r['minQuantity'],
                        'max_quantity' => $r['maxQuantity'] ?? null,
                        'price_per_sq_ft' => $r['pricePerSqFt'],
                    ]);
                } else {
                    $product->priceRanges()->create([
                        'min_quantity' => $r['minQuantity'],
                        'max_quantity' => $r['maxQuantity'] ?? null,
                        'price_per_sq_ft' => $r['pricePerSqFt'],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message'=>'Price ranges synced','ranges'=>$product->priceRanges()->orderBy('min_quantity')->get()], Response::HTTP_OK);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncPriceRanges: '.$e->getMessage());
            return response()->json(['message'=>'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // DELETE /admin/products/{product}/price-ranges/{range}
    public function deletePriceRange(Product $product, ProductPriceRange $range)
    {
        if ($range->product_id != $product->id) {
            return response()->json(['message'=>'Range does not belong to product'], Response::HTTP_FORBIDDEN);
        }
        $range->delete();
        return response()->json(['message'=>'Range deleted'], Response::HTTP_NO_CONTENT);
    }
}
