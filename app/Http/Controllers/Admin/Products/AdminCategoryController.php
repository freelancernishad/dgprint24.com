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
        $categories = Category::with('parent', 'children')
            ->whereNull('parent_id') // parent_id NULL বাদ
            ->orderBy('serial', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(50);

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
        'varients' => 'nullable',
        'tags' => 'nullable|array',
        'active' => 'nullable',
        'parent_id' => 'nullable|exists:categories,id',
        'serial' => 'nullable|integer',
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

    if (is_string($variants)) {
        $decoded = json_decode($variants, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

            // Replace " with inch symbol ″ everywhere
            array_walk_recursive($decoded, function (&$item) {
                if (is_string($item)) {
                    // convert " → ″
                    $item = str_replace('"', '″', $item);
                }
            });

            $variants = $decoded;
        } else {
            $variants = [];
        }
    }

    // Create category
    $parentId = $validatedData['parent_id'] ?? null;
    $serial = $validatedData['serial'] ?? null;

    if ($serial === null) {
        $serial = Category::where('parent_id', $parentId)->max('serial') + 1;
    }

    $category = Category::create([
        'name' => $validatedData['name'],
        'category_description' => $validatedData['categoryDescription'] ?? null,
        'category_image' => $categoryImageUrl,
        'variants' => $variants,
        'tags' => $validatedData['tags'] ?? [],
        'active' => $activeStatus,
        'parent_id' => $parentId,
        'serial' => $serial,
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
        'varients' => 'nullable',
        'tags' => 'nullable|array',
        'active' => 'nullable',
        'parent_id' => ['nullable', 'exists:categories,id', 'not_in:' . $category->id],
        'serial' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $validatedData = $validator->validated();

    // Image upload
    $categoryImageUrl = $category->category_image;
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
    $activeStatus = array_key_exists('active', $validatedData)
        ? !empty($validatedData['active'])
        : $category->active;

    // ===== Handle variants EXACTLY like store() =====
    $variants = $validatedData['varients'] ?? $category->variants;

    if (is_string($variants)) {
        $decoded = json_decode($variants, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

            // Replace " → ″
            array_walk_recursive($decoded, function (&$item) {
                if (is_string($item)) {
                    $item = str_replace('"', '″', $item);
                }
            });

            $variants = $decoded;
        } else {
            $variants = $category->variants;
        }
    }

    // Update category
    $category->update([
        'name' => $validatedData['name'] ?? $category->name,
        'category_description' => $validatedData['categoryDescription'] ?? $category->category_description,
        'category_image' => $categoryImageUrl,
        'variants' => $variants,
        'tags' => $validatedData['tags'] ?? $category->tags,
        'active' => $activeStatus,
        'parent_id' => $validatedData['parent_id'] ?? $category->parent_id,
        'serial' => $validatedData['serial'] ?? $category->serial,
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
    // public function destroy(Category $category)
    // {
    //     // চেক করুন এই ক্যাটাগরিতে কোনো প্রোডাক্ট আছে কিনা
    //     if ($category->products()->exists()) {
    //         return response()->json([
    //             'error' => 'Cannot delete category because it has associated products.'
    //         ], 409); // 409 Conflict স্ট্যাটাস কোড
    //     }

    //     // চেক করুন এই ক্যাটাগরির অধীনে কোনো সাব-ক্যাটাগরি আছে কিনা
    //     if ($category->children()->exists()) {
    //         return response()->json([
    //             'error' => 'Cannot delete category because it has sub-categories.'
    //         ], 409);
    //     }


    //     $category->delete();

    //     return response()->json(null, 204);
    // }




    public function destroy(Category $category)
    {
        $this->deleteCategoryWithChildren($category);

        return response()->json([
            'message' => 'Category and all related data deleted successfully.'
        ]);
    }

    private function deleteCategoryWithChildren($category)
    {
        foreach ($category->children as $child) {
            $this->deleteCategoryWithChildren($child);
        }

        // Products collect করো
        $products = $category->products;

        foreach ($products as $product) {
            // আগে carts delete
            $product->carts()->delete();

            // তারপর product delete
            $product->delete();
        }

        // শেষে category delete
        $category->delete();
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


    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->active = !$category->active;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category status updated successfully!',
            'data' => $category
        ]);
    }

    public function updateSerial(Request $request, $id)
    {
        $request->validate([
            'newSerial' => 'required|integer|min:1'
        ]);

        $category = Category::findOrFail($id);
        $oldSerial = $category->serial;
        $newSerial = $request->newSerial;
        $parentId = $category->parent_id;

        if ($oldSerial == $newSerial) {
            return response()->json(['message' => 'Serial is already the same.'], 200);
        }

        if ($oldSerial < $newSerial) {
            // Moving down (e.g., 2 to 5): Shift 3, 4, 5 to 2, 3, 4
            Category::where('parent_id', $parentId)
                ->whereBetween('serial', [$oldSerial + 1, $newSerial])
                ->decrement('serial');
        } else {
            // Moving up (e.g., 5 to 2): Shift 2, 3, 4 to 3, 4, 5
            Category::where('parent_id', $parentId)
                ->whereBetween('serial', [$newSerial, $oldSerial - 1])
                ->increment('serial');
        }

        $category->serial = $newSerial;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category serial updated and others shifted successfully!',
            'data' => $category
        ]);
    }


}
