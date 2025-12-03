<?php

namespace App\Http\Controllers\Admin\Products\ProductEdit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use App\Models\PriceConfiguration;
use App\Models\PriceConfigurationShipping;
use App\Models\PriceConfigurationTurnaround;

class PriceConfigChildController extends Controller
{
    // POST /admin/price-configs/{config}/shippings
    public function addShipping(Request $request, PriceConfiguration $config)
    {
        $v = Validator::make($request->all(), [
            'shippingLabel' => 'required|string',
            'shippingValue' => 'required',
            'price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $s = $config->shippings()->create($v->validated());
        return response()->json(['message'=>'Shipping added', 'shipping'=>$s], Response::HTTP_CREATED);
    }

    // DELETE /admin/price-configs/{config}/shippings/{id}
    public function deleteShipping(PriceConfiguration $config, $id)
    {
        $s = $config->shippings()->where('id', $id)->first();
        if (!$s) return response()->json(['message'=>'Shipping not found for this config'], Response::HTTP_NOT_FOUND);
        $s->delete();
        return response()->json(['message'=>'Shipping deleted'], Response::HTTP_NO_CONTENT);
    }

    // POST /admin/price-configs/{config}/turnarounds
    public function addTurnaround(Request $request, PriceConfiguration $config)
    {
        $v = Validator::make($request->all(), [
            'turnaroundLabel' => 'required|string',
            'turnaroundValue' => 'required',
            'price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        if ($v->fails()) return response()->json(['errors'=>$v->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);

        $t = $config->turnarounds()->create($v->validated());
        return response()->json(['message'=>'Turnaround added', 'turnaround'=>$t], Response::HTTP_CREATED);
    }

    // DELETE /admin/price-configs/{config}/turnarounds/{id}
    public function deleteTurnaround(PriceConfiguration $config, $id)
    {
        $t = $config->turnarounds()->where('id', $id)->first();
        if (!$t) return response()->json(['message'=>'Turnaround not found for this config'], Response::HTTP_NOT_FOUND);
        $t->delete();
        return response()->json(['message'=>'Turnaround deleted'], Response::HTTP_NO_CONTENT);
    }
}
