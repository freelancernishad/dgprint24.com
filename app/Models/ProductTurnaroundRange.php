<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTurnaroundRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'min_quantity',
        'max_quantity',
        'discount',
        'turnarounds', // JSON column to store turnaround options
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'discount' => 'decimal:2',
        'turnarounds' => 'array', // Cast the JSON column to array
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
