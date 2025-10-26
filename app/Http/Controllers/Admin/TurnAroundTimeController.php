<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TurnAroundTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TurnAroundTimeController extends Controller
{
    /**
     * Display a listing of the resource.
     * সব টার্নআরাউন্ড টাইমের লিস্ট দেখানোর জন্য।
     */
    public function index()
    {
        $turnaroundTimes = TurnAroundTime::with('category:id,name,category_id')
            ->orderBy('category_name')
            ->orderBy('id','desc')
            ->paginate(50);

        return response()->json($turnaroundTimes);
    }

    /**
     * Store a newly created resource in storage.
     * নতুন টার্নআরাউন্ড টাইম তৈরি করার জন্য।
     */
    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_name' => 'nullable|string|max:255',
            'category_id' => 'nullable|string|exists:categories,category_id',
            'turnaround_label' => 'nullable|string|max:255',
            'turnaround_value' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string',
            'runsize' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $turnaroundTime = TurnAroundTime::create($validatedData);

        return response()->json($turnaroundTime, 201);
    }

    /**
     * Display the specified resource.
     * নির্দিষ্ট একটি টার্নআরাউন্ড টাইম দেখানোর জন্য।
     */
    public function show(TurnAroundTime $turnaround_time)
    {
        // রিলেশন লোড করে দেখানো হচ্ছে
        $turnaround_time->load('category:id,name,category_id');
        return response()->json($turnaround_time);
    }

    /**
     * Update the specified resource in storage.
     * টার্নআরাউন্ড টাইম আপডেট করার জন্য।
     */
    public function update(Request $request, TurnAroundTime $turnaround_time)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'category_name' => 'required|string|max:255',
            'category_id' => 'required|string|exists:categories,category_id',
            'turnaround_label' => 'required|string|max:255',
            'turnaround_value' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string',
            'runsize' => 'nullable|integer|min:1',
        ]);

        $turnaround_time->update($validatedData);

        return response()->json($turnaround_time);
    }

    /**
     * Remove the specified resource from storage.
     * টার্নআরাউন্ড টাইম ডিলিট করার জন্য।
     */
    public function destroy(TurnAroundTime $turnaround_time)
    {
        $turnaround_time->delete();

        return response()->json(null, 204);
    }
}
