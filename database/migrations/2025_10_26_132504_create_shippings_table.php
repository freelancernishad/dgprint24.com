<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shippings', function (Blueprint $table) {
            $table->id();
            $table->string('shipping_id')->unique(); // ইউনিক আইডি যেমন: ship-xxxxxxxx
            $table->string('category_name')->nullable(); // ক্যাটাগরি নাম
            $table->string('category_id')->nullable(); // Category মডেলের category_id এর সাথে রিলেশন
            $table->string('shipping_label')->nullable(); // যেমন: Standard Shipping
            $table->integer('shipping_value')->nullable(); // যেমন: 5 (দিন বা অন্য কিছু)
            $table->decimal('price', 8, 2)->nullable(); // দাম, যেমন: 15.00
            $table->text('note')->nullable(); // অতিরিক্ত নোট
            $table->integer('runsize')->nullable(); // রান সাইজ
            $table->timestamps();

            // ইনডেক্স যোগ করা হলো দ্রুত খোঁজার জন্য
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shippings');
    }
};
