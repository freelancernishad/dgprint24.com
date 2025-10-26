<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     * সব ট্যাক্সের লিস্ট দেখানোর জন্য।
     */
    public function index()
    {
        $taxes = Tax::orderBy('country')
            ->orderBy('state')
            ->paginate(50);

        return response()->json($taxes);
    }

    /**
     * Store a newly created resource in storage.
     * নতুন ট্যাক্স তৈরি করার জন্য।
     */
    public function store(Request $request)
    {
        $rules = [
            'country' => [
            'required',
            'string',
            'max:255',
            Rule::unique('taxes')->where(function ($query) use ($request) {
                return $query->where('state', $request->state);
            }),
            ],
            'state' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:100',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $tax = Tax::create($validatedData);

        return response()->json($tax, 201);
    }

    /**
     * Display the specified resource.
     * নির্দিষ্ট একটি ট্যাক্স দেখানোর জন্য।
     */
    public function show(Tax $tax)
    {
        return response()->json($tax);
    }

    /**
     * Update the specified resource in storage.
     * ট্যাক্স আপডেট করার জন্য।
     */
    public function update(Request $request, Tax $tax)
    {
        $rules = [
            'country' => [
            'required',
            'string',
            'max:255',
            Rule::unique('taxes')->where(function ($query) use ($request) {
                return $query->where('state', $request->state);
            })->ignore($tax->id),
            ],
            'state' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:100',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $tax->update($validatedData);

        return response()->json($tax);
    }

    /**
     * Remove the specified resource from storage.
     * ট্যাক্স ডিলিট করার জন্য।
     */
    public function destroy(Tax $tax)
    {
        $tax->delete();

        return response()->json(null, 204);
    }
}
