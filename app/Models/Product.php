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
        'dynamicOptions',
        'job_sample_price',
        'digital_proof_price',
    ];

    protected $casts = [
        'dynamicOptions' => 'array',
        'active' => 'boolean',
        'popular_product' => 'boolean',
        'base_price' => 'decimal:2',
        'job_sample_price' => 'decimal:2',
        'digital_proof_price' => 'decimal:2',
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

    // নতুন রিলেশনশিপ যোগ করুন
    public function priceRanges()
    {
        return $this->hasMany(ProductPriceRange::class);
    }

    public function turnaroundRanges()
    {
        return $this->hasMany(ProductTurnaroundRange::class);
    }

    public function shippingRanges()
    {
        return $this->hasMany(ProductShippingRange::class);
    }
}
