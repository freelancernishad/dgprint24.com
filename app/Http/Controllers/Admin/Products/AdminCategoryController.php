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
        $categories = Category::with('parent', 'children')
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
            'name' => 'required|string|max:255|unique:categories,name',
            'categoryDescription' => 'nullable|string',
            'catagoryImage' => 'nullable|file|mimes:jpeg,jpg,png,gif,bmp,webp,svg,tiff,ico',
            'varients' => 'nullable', // string or array
            'tags' => 'nullable|array',
            'active' => 'nullable',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $categoryImageUrl = null;

        // Upload category image to S3
        if ($request->hasFile('catagoryImage')) {
            $file = $request->file('catagoryImage');
            $filename = time() . '_' . $file->getClientOriginalName();
            $label = 'category';

            $categoryImageUrl = (new FileUploadService())->uploadFileToS3(
                $file,
                'dgprint24/uploads/images/category/' . $label . '_' . $filename
            );
        }

        // Active status
        $activeStatus = !empty($validatedData['active']);

        // Handle variants (string or array)
        $variants = $validatedData['varients'] ?? [];

        // If incoming value is a JSON string, decode it
        if (is_string($variants)) {
            $decoded = json_decode($variants, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

                // Sanitize all inner strings
                array_walk_recursive($decoded, function (&$item) {
                    if (is_string($item)) {
                        // Make JSON safe
                        $item = htmlspecialchars($item, ENT_QUOTES);
                    }
                });

                $variants = $decoded;
            } else {
                $variants = []; // invalid JSON fallback
            }
        }

        // Create Category
        $category = Category::create([
            'name' => $validatedData['name'],
            'category_description' => $validatedData['categoryDescription'] ?? null,
            'category_image' => $categoryImageUrl,
            'variants' => $variants,
            'tags' => $validatedData['tags'] ?? [],
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
        $validator = validator($request->all(), [
            'name' => [
            'nullable',
            'string',
            'max:255',
            Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'categoryDescription' => 'nullable|string',
            'catagoryImage' => 'nullable|file|mimes:jpeg,jpg,png,gif,bmp,webp,svg,tiff,ico',
            'varients' => 'nullable', // পরিবর্তন: string থেকে array
            'tags' => 'nullable|array',    // পরিবর্তন: string থেকে array
            'active' => 'nullable',
            'parent_id' => ['nullable', 'exists:categories,id', 'not_in:' . $category->id],
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
            $label = 'category';

            $categoryImageUrl = (new FileUploadService())->uploadFileToS3(
                $file,
                'dgprint24/uploads/images/category/' . $label . '_' . $filename
            );
        }
        // আর json_decode করার দরকার নেই
        $category->update([
            'name' => $validatedData['name'],
            'category_description' => $validatedData['categoryDescription'] ?? $category->category_description,
            'category_image' => $categoryImageUrl ?? $category->category_image,
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


    public function toggleShowInNavbar($id)
    {
        $category = Category::findOrFail($id);

        // toggle value
        $category->show_in_navbar = !$category->show_in_navbar;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category navbar visibility updated successfully!',
            'data' => $category
        ]);
    }


}
