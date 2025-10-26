<?php

namespace App\Http\Controllers\Global\Products;

use App\Models\Category;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * ফ্রন্টএন্ডে ড্রপডাউন বা মেনুতে দেখানোর জন্য সব অ্যাকটিভ ক্যাটাগরির হায়ারার্কিক্যাল লিস্ট।
     * GET /api/categories
     */
    public function index()
    {
        $categories = Category::with('children:id,name,parent_id,category_id')
            ->where('parent_id', null) // শুধুমাত্র রুট লেভেলের ক্যাটাগরি নিচ্ছি
            ->where('active', true) // শুধুমাত্র অ্যাকটিভ ক্যাটাগরি
            ->select('id',         'name',
        'parent_id',
        'category_description',
        'category_image',
        'variants',
        'tags',
        'active')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * সব অ্যাকটিভ ক্যাটাগরির ফ্ল্যাট লিস্ট পেতে।
     * GET /api/categories/flat-list
     */
    public function flatList()
    {
        $categories = Category::where('active', true)
            ->select('id', 'name', 'category_id', 'parent_id')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    /**
     * Display the specified resource.
     * category_id দিয়ে নির্দিষ্ট ক্যাটাগরির তথ্য দেখানোর জন্য।
     * GET /api/categories/{category}
     */
    public function show(Category $category)
    {
        // নিশ্চিত করুন ক্যাটাগরিটি অ্যাকটিভ
        if (!$category->active) {
            return response()->json(['error' => 'Category not found.'], 404);
        }

        return response()->json($category);
    }
}
