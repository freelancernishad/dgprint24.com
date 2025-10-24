<?php

namespace App\Http\Controllers\Global\Products;

use App\Models\Category;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * ফ্রন্টএন্ডে ড্রপডাউন বা মেনুতে দেখানোর জন্য সব অ্যাকটিভ ক্যাটাগরির লিস্ট।
     */
    public function index()
    {
        // শুধুমাত্র প্রয়োজনীয় কলামগুলো নিন এবং অ্যাকটিভ ক্যাটাগরি ফিল্টার করুন
        // এখানে প্যারেন্ট-চাইল্ড রিলেশনও লোড করা হচ্ছে যাতে ফ্রন্টএন্ড সহজে ট্রি স্ট্রাকচার বানাতে পারে
        $categories = Category::with('children:id,name,parent_id,category_id')
            ->where('parent_id', null) // শুধুমাত্র রুট লেভেলের ক্যাটাগরি নিচ্ছি
            ->select('id', 'name', 'category_id')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }
}
