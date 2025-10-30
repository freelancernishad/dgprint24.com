<?php

namespace App\Http\Controllers\Admin\Products;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Services\FileSystem\FileUploadService;

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
     * নতুন ক্যাটাগরি তৈরি করা।
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'categoryName' => 'required|string|max:255|unique:categories,name',
            'categoryDescription' => 'nullable|string',
            'catagoryImage' => 'nullable|file|mimes:jpeg,jpg,png,gif',
            'varients' => 'nullable', // পরিবর্তন: string থেকে array
            'tags' => 'nullable|array',    // পরিবর্তন: string থেকে array
            'active' => 'nullable',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();


        $categoryImageUrl = null;

        // যদি ছবি আসে, S3 এ আপলোড কর
        if ($request->hasFile('catagoryImage')) {
            $file = $request->file('catagoryImage');
            $filename = time() . '_' . $file->getClientOriginalName();
            $resizedContent = file_get_contents($file->getRealPath()); // যদি চাইছ resize করো, পরে কোডে adjust করতে পারো
            $label = 'category';

            $categoryImageUrl = (new FileUploadService())->uploadContentToS3(
                $resizedContent,
                'dgprint24/uploads/images/category/' . $label . '_' . $filename
            );
        }

        $activeStatus = false; // ডিফল্টভাবে false সেট করুন
        if ($validatedData['active'] == true) {
            $activeStatus = true; 
        }
        // আর json_decode করার দরকার নেই, কারণ Laravel স্বয়ংক্রিয়ভাবে অ্যারে নিবে
        $category = Category::create([
            'name' => $validatedData['categoryName'],
            'category_description' => $validatedData['categoryDescription'] ?? null,
            'category_image' => $categoryImageUrl,
            'variants' => $validatedData['varients'], // সরাসরি অ্যারে ব্যবহার করুন
            'tags' => $validatedData['tags'],        // সরাসরি অ্যারে ব্যবহার করুন
            'active' => $activeStatus,
            'parent_id' => $validatedData['parent_id'] ?? null,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Update the specified resource in storage.
     * কোনো ক্যাটাগরির নাম বা প্যারেন্ট পরিবর্তন করা।
     */
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'categoryName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'categoryDescription' => 'nullable|string',
            'catagoryImage' => 'nullable|string|url',
            'varients' => 'nullable|array', // পরিবর্তন: string থেকে array
            'tags' => 'nullable|array',    // পরিবর্তন: string থেকে array
            'active' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $category->id,
        ]);

        // আর json_decode করার দরকার নেই
        $category->update([
            'name' => $validatedData['categoryName'],
            'category_description' => $validatedData['categoryDescription'] ?? $category->category_description,
            'category_image' => $validatedData['catagoryImage'] ?? $category->category_image,
            'variants' => $validatedData['varients'] ?? $category->variants, // সরাসরি অ্যারে ব্যবহার করুন
            'tags' => $validatedData['tags'] ?? $category->tags,        // সরাসরি অ্যারে ব্যবহার করুন
            'active' => $validatedData['active'] ?? $category->active,
            'parent_id' => $validatedData['parent_id'] ?? $category->parent_id,
        ]);

        return response()->json($category);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
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
