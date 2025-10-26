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
        Schema::create('turn_around_times', function (Blueprint $table) {
            $table->id();
            $table->string('turnaround_id')->unique(); // ইউনিক আইডি যেমন: cmadgyr5l00105xqni2ufe945
            $table->string('name')->nullable(); // যেমন: Standard, Express
            $table->string('category_name')->nullable();
            $table->string('category_id')->nullable(); // Category মডেলের category_id এর সাথে রিলেশন
            $table->string('turnaround_label')->nullable(); // যেমন: 5 Business Days
            $table->integer('turnaround_value')->nullable(); // যেমন: 5
            $table->decimal('price', 8, 2)->nullable(); // দাম, যেমন: 6.00
            $table->decimal('discount', 5, 2)->nullable(); // ডিসকাউন্ট, যেমন: 10.50
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
        Schema::dropIfExists('turn_around_times');
    }
};
