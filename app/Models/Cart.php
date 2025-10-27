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
        'price_at_time',           // পণ্যটি কেনার সময় যে মূল্য ছিল
        'session_id',              // গেস্ট ইউজারদের জন্য
        'options',                 // প্রোডাক্ট অপশন (JSON), যেমন: ['color' => 'red', 'size' => 'M']
        'price_breakdown',         // মূল্য বিভাজন (JSON), যেমন: ['subtotal' => 500, 'shipping' => 50]
        'status',                  // pending, ordered, abandoned
    ];

    protected $casts = [
        'options' => 'array',
        'price_breakdown' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * কার্ট আইটেমের মোট মূল্য ক্যালকুলেট করে
     * যদি price_breakdown থাকে সেখান থেকে, নাহলে price_at_time * quantity
     */
    public function getTotalAttribute()
    {
        if (isset($this->price_breakdown['total'])) {
            return $this->price_breakdown['total'];
        }
        return $this->price_at_time * $this->quantity;
    }
}
