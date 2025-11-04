<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceConfigurationOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_configuration_id',
        'key',
        'value',
    ];

    /**
     * Relation to PriceConfiguration
     */
    public function priceConfiguration()
    {
        return $this->belongsTo(PriceConfiguration::class);
    }
}
