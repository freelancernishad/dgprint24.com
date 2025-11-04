<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'runsize',
        'price',
        'discount',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
        'price' => 'decimal:2',
        'discount' => 'decimal:2'
    ];

    public function optionsRel()
    {
        return $this->hasMany(PriceConfigurationOption::class);
    }




    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shippings()
    {
        return $this->hasMany(PriceConfigurationShipping::class);
    }

    public function turnarounds()
    {
        return $this->hasMany(PriceConfigurationTurnaround::class);
    }
}
