<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class AdminCategoryController extends Controller
{
     /**
     * Display a listing of the resource.
     * অ্যাডমিন প্যানেলে সব ক্যাটাগরির লিস্ট দেখানোর জন্য।
     */
    public function index()
    {
        // অ্যাডমিনকে সব ক্যাটাগরি দেখানো হবে, এমনকি ইনঅ্যাক্টিভও
        $categories = Category::with('parent:id,name', 'children:id,name,parent_id')
            ->orderBy('name')
            ->paginate(50); // পেজিনেশন যোগ করা হয়েছে

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     * নতুন ক্যাটাগরি তৈরি করা (যদিও আপনি ব্যবহার নাও করতে পারেন)।
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create([
            'name' => $validatedData['name'],
            'category_id' => 'cat-' . Str::uuid(),
            'parent_id' => $validatedData['parent_id'] ?? null,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     * কোনো ক্যাটাগরির নাম বা প্যারেন্ট পরিবর্তন করা।
     */
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($category->id), // নিজের নাম ছাড়া অন্য কারো সাথে মিললে হবে না
            ],
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $category->id, // নিজেকে নিজের প্যারেন্ট বানানো যাবে না
        ]);

        $category->update($validatedData);

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     * ক্যাটাগরি ডিলিট করা।
     */
    public function destroy(Category $category)
    {
        // চেক করুন এই ক্যাটাগরিতে কোনো প্রোডাক্ট আছে কিনা
        if ($category->products()->exists()) {
            return response()->json([
                'error' => 'Cannot delete category because it has associated products.'
            ], 409); // 409 Conflict স্ট্যাটাস কোড
        }

        // চেক করুন এই ক্যাটাগরির অধীনে কোনো সাব-ক্যাটাগরি আছে কিনা
        if ($category->children()->exists()) {
            return response()->json([
                'error' => 'Cannot delete category because it has sub-categories.'
            ], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
