<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price_at_time',
        'session_id',   // For guest users
        'options',      // Product options (JSON)
        'status',       // pending, ordered, cancelled
    ];

    /**
     * Cast options field to array automatically
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * User relation
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Product relation
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
