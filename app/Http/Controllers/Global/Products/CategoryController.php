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
        ->whereNotNull('parent_id')
            // ->where('parent_id', null) // শুধুমাত্র রুট লেভেলের ক্যাটাগরি নিচ্ছি
            ->where('active', true) // শুধুমাত্র অ্যাকটিভ ক্যাটাগরি
            ->select('id',         'name',
        'category_id',
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
     * Display only child categories (parent_id exists).
     * যেসব ক্যাটাগরির parent_id আছে শুধু সেগুলো দেখাবে
     * GET /api/categories/children
     */
    public function parentCategories()
    {
        $categories = Category::with('children')->where('parent_id', null) // শুধুমাত্র চাইল্ড ক্যাটাগরি
            ->where('active', true) // শুধুমাত্র অ্যাকটিভ ক্যাটাগরি
            ->select(
                'id',
                'name',
                'category_id',
                'parent_id',
                'category_description',
                'category_image',
                'variants',
                'tags',
                'active'
            )
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


    /**
     * Get a list of categories that are set to show in the navbar.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNavbarCategories()
    {
        // Find all categories where the 'show_in_navbar' column is true
        $categories = Category::where('show_in_navbar', true)->get();

        // Return the list of categories as a JSON response
        return response()->json([
            'success' => true,
            'message' => 'Navbar categories retrieved successfully!',
            'data' => $categories
        ]);
    }

    /**
     * Get a list of categories that are set to show in the navbar.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNavbarCategoriesWithProducts()
    {
        // Find all categories where the 'show_in_navbar' column is true
        $categories = Category::with(['products' => function($query) {
            $query->select('id', 'product_id', 'product_name','category_id');
        }])
        ->where('show_in_navbar', true)
        ->select('id', 'category_id', 'name', 'category_image')
        ->get();

        // Return the list of categories as a JSON response
        return response()->json([
            'success' => true,
            'message' => 'Navbar categories retrieved successfully!',
            'data' => $categories
        ]);
    }


}
