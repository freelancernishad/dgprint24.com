<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'category_id',
        'product_name',
        'product_description',
        'thumbnail',
        'base_price',
        'active',
        'popular_product',
        'product_options',
    ];

    protected $casts = [
        'product_options' => 'array',
        'active' => 'boolean',
        'popular_product' => 'boolean',
        'base_price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class)->orderBy('sort_order');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function priceConfigurations()
    {
        return $this->hasMany(PriceConfiguration::class);
    }
}
