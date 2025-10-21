<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceConfigurationTurnaround extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', // Primary key
        'price_config_id',
        'turnaroundLabel',
        'turnaroundValue',
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
