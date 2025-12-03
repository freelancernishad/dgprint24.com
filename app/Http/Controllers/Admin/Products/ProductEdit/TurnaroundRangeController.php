<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

use App\Models\ProductTurnaroundRange;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TurnaroundRangeController extends Controller
{
    public function getTurnaroundRanges(Product $product)
    {
        $turnaroundRanges = $product->turnaroundRanges()->orderBy('min_quantity')->get();
        return response()->json(['turnaroundRanges' => $turnaroundRanges], Response::HTTP_OK);
    }

    public function addTurnaroundRange(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'min_quantity' => 'required|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0',
            'discount' => 'nullable|numeric|min:0',
            'turnarounds' => 'nullable|array',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = $v->validated();
        $range = $product->turnaroundRanges()->create([
            'min_quantity' => $data['min_quantity'],
            'max_quantity' => $data['max_quantity'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'turnarounds' => $data['turnarounds'] ?? [],
        ]);

        return response()->json(['message'=>'Turnaround range added','range'=>$range], Response::HTTP_CREATED);
    }

    public function syncTurnaroundRanges(Request $request, Product $product)
    {
        $v = Validator::make($request->all(), [
            'ranges' => 'required|array',
            'ranges.*.id' => 'nullable|integer|exists:product_turnaround_ranges,id',
            'ranges.*.min_quantity' => 'required|integer|min:0',
            'ranges.*.max_quantity' => 'nullable|integer|min:0',
            'ranges.*.discount' => 'nullable|numeric|min:0',
            'ranges.*.turnarounds' => 'nullable|array',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $ranges = $v->validated()['ranges'];

        DB::beginTransaction();
        try {
            $incomingIds = collect($ranges)->pluck('id')->filter()->values()->all();
            $product->turnaroundRanges()->whereNotIn('id', $incomingIds)->delete();

            foreach ($ranges as $r) {
                if (!empty($r['id'])) {
                    $product->turnaroundRanges()->where('id', $r['id'])->update([
                        'min_quantity' => $r['min_quantity'],
                        'max_quantity' => $r['max_quantity'] ?? null,
                        'discount' => $r['discount'] ?? 0,
                        'turnarounds' => $r['turnarounds'] ?? [],
                    ]);
                } else {
                    $product->turnaroundRanges()->create([
                        'min_quantity' => $r['min_quantity'],
                        'max_quantity' => $r['max_quantity'] ?? null,
                        'discount' => $r['discount'] ?? 0,
                        'turnarounds' => $r['turnarounds'] ?? [],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message'=>'Turnaround ranges synced','ranges'=>$product->turnaroundRanges()->orderBy('min_quantity')->get()], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('syncTurnaroundRanges: '.$e->getMessage());
            return response()->json(['message'=>'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteTurnaroundRange(Product $product, ProductTurnaroundRange $range)
    {
        if ($range->product_id !== $product->id) {
            return response()->json(['message'=>'Range does not belong to product'], Response::HTTP_FORBIDDEN);
        }
        $range->delete();
        return response()->json(['message'=>'Turnaround range deleted'], Response::HTTP_NO_CONTENT);
    }
}
