<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
        'category_description',
        'category_image',
        'variants',
        'tags',
        'active',
    ];

    // JSON কাস্টিং যোগ করুন
    protected $casts = [
        'variants' => 'array',
        'tags' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // যখন নতুন ক্যাটাগরি তৈরি হবে
        static::creating(function ($category) {
            // নাম থেকে category_id তৈরি করুন
            $category->category_id = $category->generateCategoryId($category->name);
        });

        // যখন ক্যাটাগরির নাম আপডেট হবে
        static::updating(function ($category) {
            // যদি নাম পরিবর্তন করা হয়
            if ($category->isDirty('name')) {
                $category->category_id = $category->generateCategoryId($category->name);
            }
        });
    }

    /**
     * নাম থেকে category_id তৈরি করার মেথড
     */
    private function generateCategoryId($name)
    {
        // নামকে ছোট হাতের অক্ষরে রূপান্তর করুন
        $slug = Str::lower($name);

        // স্পেস এবং বিশেষ অক্ষরগুলো হাইফেন দিয়ে প্রতিস্থাপন করুন
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // শুরু এবং শেষের হাইফেনগুলো সরান
        $slug = trim($slug, '-');

        // ইউনিক আইডি যোগ করুন
        $uniqueId = Str::random(8);

        // বর্তমান তারিখ যোগ করুন
        $date = now()->format('mdY');

        return "{$slug}-{$date}-{$uniqueId}";
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
