<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceConfigurationShipping extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', // Primary key
        'price_configuration_id',
        'shippingLabel',
        'shippingValue',
        'price',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:2'
    ];

    // Primary key টি string হওয়ায় এই সেটিংস প্রয়োজন
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public function priceConfiguration()
    {
        return $this->belongsTo(PriceConfiguration::class);
    }
}
