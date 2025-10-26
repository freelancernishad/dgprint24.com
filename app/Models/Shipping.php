<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shipping extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shippings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // 'shipping_id' এখন আর fillable এর মধ্যে থাকবে না, কারণ এটি স্বয়ংক্রিয়ভাবে তৈরি হবে
        'category_name',
        'category_id',
        'shipping_label',
        'shipping_value',
        'price',
        'note',
        'runsize',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'runsize' => 'integer',
        'shipping_value' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // যখন নতুন রেকর্ড তৈরি হবে
        static::creating(function ($shipping) {
            if (empty($shipping->shipping_id)) {
                $shipping->shipping_id = $shipping->generateShippingId($shipping->shipping_label);
            }
        });

        // যখন রেকর্ড আপডেট হবে
        static::updating(function ($shipping) {
            // যদি লেবেল পরিবর্তন করা হয়, তাহলে shipping_id ও আপডেট করুন
            if ($shipping->isDirty('shipping_label')) {
                $shipping->shipping_id = $shipping->generateShippingId($shipping->shipping_label);
            }
        });
    }

    /**
     * লেবেল থেকে একটি ইউনিক shipping_id তৈরি করার মেথড
     */
    private function generateShippingId($label)
    {
        // লেবেলকে ছোট হাতের অক্ষরে রূপান্তর করুন
        $slug = Str::lower($label);

        // স্পেস এবং বিশেষ অক্ষরগুলো হাইফেন দিয়ে প্রতিস্থাপন করুন
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // শুরু এবং শেষের হাইফেনগুলো সরান
        $slug = trim($slug, '-');

        // ইউনিক আইডি যোগ করুন
        $uniqueId = Str::random(8);

        // বর্তমান তারিখ যোগ করুন
        $date = now()->format('m-d-Y');

        return "ship-{$slug}-{$date}-{$uniqueId}";
    }

    /**
     * এই শিপিং অপশনটি কোন ক্যাটাগরির জন্য
     */
    public function category()
    {
        // category_id স্ট্রিং, তাই আমরা Category মডেলের category_id কলামের সাথে রিলেশন করছি
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }
}
