<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipping;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingController extends Controller
{
    /**
     * Display a listing of the resource.
     * সব শিপিং অপশনের লিস্ট দেখানোর জন্য।
     */
    public function index()
    {
        $shippings = Shipping::with('category:id,name,category_id')
            ->orderBy('category_name')
            ->orderBy('shipping_value')
            ->paginate(50);

        return response()->json($shippings);
    }

    /**
     * Store a newly created resource in storage.
     * নতুন শিপিং অপশন তৈরি করার জন্য।
     */
    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'category_name' => 'nullable|string|max:255',
            'category_id' => 'nullable|string|exists:categories,category_id',
            'shipping_label' => 'required|string|max:255',
            'shipping_value' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'runsize' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $shipping = Shipping::create($validatedData);

        return response()->json($shipping, 201);
    }

    /**
     * Display the specified resource.
     * নির্দিষ্ট একটি শিপিং অপশন দেখানোর জন্য।
     */
    public function show(Shipping $shipping)
    {
        // রিলেশন লোড করে দেখানো হচ্ছে
        $shipping->load('category:id,name,category_id');
        return response()->json($shipping);
    }

    /**
     * Update the specified resource in storage.
     * শিপিং অপশন আপডেট করার জন্য।
     */
    public function update(Request $request, Shipping $shipping)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'category_name' => 'nullable|string|max:255',
            'category_id' => 'nullable|string|exists:categories,category_id',
            'shipping_label' => 'required|string|max:255',
            'shipping_value' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'runsize' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $shipping->update($validatedData);

        return response()->json($shipping);
    }

    /**
     * Remove the specified resource from storage.
     * শিপিং অপশন ডিলিট করার জন্য।
     */
    public function destroy(Shipping $shipping)
    {
        $shipping->delete();

        return response()->json(['message' => 'Shipping option deleted successfully.']);
    }
}
