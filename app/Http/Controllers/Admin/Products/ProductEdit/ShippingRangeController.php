<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

use App\Models\ProductShippingRange;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ShippingRangeController extends Controller
{
    public function getShippingRanges(Product $product)
    {
        $shippingRanges = $product->shippingRanges()->orderBy('min_quantity')->get();
        return response()->json(['shippingRanges' => $shippingRanges], Response::HTTP_OK);
    }

    public function addShippingRange(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'minQuantity' => 'required|integer|min:0',
            'maxQuantity' => 'nullable|integer|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shippings' => 'nullable|array',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = $v->validated();
        $range = $product->shippingRanges()->create([
            'min_quantity' => $data['minQuantity'],
            'max_quantity' => $data['maxQuantity'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'shippings' => $data['shippings'] ?? [],
        ]);

        return response()->json(['message'=>'Shipping range added','range'=>$range], Response::HTTP_CREATED);
    }

    public function syncShippingRanges(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'ranges' => 'required|array',
            'ranges.*.id' => 'nullable|integer|exists:product_shipping_ranges,id',
            'ranges.*.minQuantity' => 'required|integer|min:0',
            'ranges.*.maxQuantity' => 'nullable|integer|min:0',
            'ranges.*.discount' => 'nullable|numeric|min:0',
            'ranges.*.shippings' => 'nullable|array',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $ranges = $v->validated()['ranges'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($ranges)->pluck('id')->filter()->values()->all();
            $product->shippingRanges()->whereNotIn('id', $incomingIds)->delete();

            foreach ($ranges as $r) {
                if (!empty($r['id'])) {
                    $product->shippingRanges()->where('id', $r['id'])->update([
                        'min_quantity' => $r['minQuantity'],
                        'max_quantity' => $r['maxQuantity'] ?? null,
                        'discount' => $r['discount'] ?? 0,
                        'shippings' => $r['shippings'] ?? [],
                    ]);
                } else {
                    $product->shippingRanges()->create([
                        'min_quantity' => $r['minQuantity'],
                        'max_quantity' => $r['maxQuantity'] ?? null,
                        'discount' => $r['discount'] ?? 0,
                        'shippings' => $r['shippings'] ?? [],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message'=>'Shipping ranges synced','ranges'=>$product->shippingRanges()->orderBy('min_quantity')->get()], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncShippingRanges: '.$e->getMessage());
            return response()->json(['message'=>'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteShippingRange(Product $product, ProductShippingRange $range)
    {
        if ($range->product_id != $product->id) {
            return response()->json(['message'=>'Range does not belong to product'], Response::HTTP_FORBIDDEN);
        }
        $range->delete();
        return response()->json(['message'=>'Shipping range deleted'], Response::HTTP_NO_CONTENT);
    }
}
