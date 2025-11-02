<?php

namespace App\Http\Controllers\Postgres;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Postgres\PgCategory;
use Illuminate\Http\Request;

class PostgresController extends Controller
{
    // 🔹 Load all categories from PostgreSQL
    public function GetCategories()
    {
        $categories = PgCategory::all();

        foreach ($categories as $category) {
            $category = Category::firstOrCreate(
                ['category_id' => $category->category_id],
                [

                    'name' => $category->categoryName,
                    'category_id' => $category->categoryId,
                    'category_description' => $category->description,
                    'category_image' => $category->imageLink,
                    'variants' => json_decode($category->variants),
                    'tags' => json_decode($category->tags),
                    'active' => $category->active,
                    'show_in_navbar' => $category->showInNavbar,

                ]

            );
        }

        return response()->json($categories);
    }


}
