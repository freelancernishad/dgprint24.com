<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductDimensionPricing extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'minwidth',
        'maxwidth',
        'minheight',
        'maxheight',
        'basePricePerSqFt',
    ];

    protected $casts = [
        'minwidth' => 'decimal:2',
        'maxwidth' => 'decimal:2',
        'minheight' => 'decimal:2',
        'maxheight' => 'decimal:2',
        'basePricePerSqFt' => 'decimal:2',
    ];
}
