<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TurnAroundTime extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'turn_around_times';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'turnaround_id',
        'name',
        'category_name',
        'category_id',
        'turnaround_label',
        'turnaround_value',
        'price',
        'discount',
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
        'discount' => 'decimal:2',
        'runsize' => 'integer',
        'turnaround_value' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // যখন নতুন রেকর্ড তৈরি হবে
        static::creating(function ($turnaround) {
            if (empty($turnaround->turnaround_id)) {
                $turnaround->turnaround_id = $turnaround->generateTurnaroundId($turnaround->name);
            }
        });

        // যখন রেকর্ড আপডেট হবে
        static::updating(function ($turnaround) {
            // যদি নাম পরিবর্তন করা হয়, তাহলে turnaround_id ও আপডেট করুন
            if ($turnaround->isDirty('name')) {
                $turnaround->turnaround_id = $turnaround->generateTurnaroundId($turnaround->name);
            }
        });
    }

    /**
     * নাম থেকে একটি ইউনিক turnaround_id তৈরি করার মেথড
     */
    private function generateTurnaroundId($name)
    {
        // নামকে ছোট হাতের অক্ষরে রূপান্তর করুন
        $slug = Str::lower($name);

        // স্পেস এবং বিশেষ অক্ষরগুলো হাইফেন দিয়ে প্রতিস্থাপন করুন
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // শুরু এবং শেষের হাইফেনগুলো সরান
        $slug = trim($slug, '-');

        // ইউনিক আইডি যোগ করুন
        $uniqueId = Str::random(8);

        // বর্তমান তারিখ যোগ করুন
        $date = now()->format('mdY');

        return "tat-{$slug}-{$date}-{$uniqueId}";
    }

    /**
     * এই টার্নআরাউন্ড টাইমটি কোন ক্যাটাগরির জন্য
     */
    public function category()
    {
        // category_id স্ট্রিং, তাই আমরা Category মডেলের category_id কলামের সাথে রিলেশন করছি
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }
}
