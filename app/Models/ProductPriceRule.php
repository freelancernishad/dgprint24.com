<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceRule extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'value_type',
        'value',
        'active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function ($rule) {

            // যদি এই rule টা active করা হয়
            if ($rule->active) {

                static::where('active', true)
                    ->where('id', '!=', $rule->id)
                    ->update(['active' => false]);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
