<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // User and Product Relations
            $table->foreignId('user_id')
                  ->nullable() // গেস্ট ইউজারদের জন্য nullable
                  ->constrained('users')
                  ->onDelete('cascade'); // ইউজার ডিলিট হলে তার কার্টও ডিলিট হবে

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('restrict'); // প্রোডাক্ট যদি কোনো কার্টে থাকে, তাহলে ডিলিট করা যাবে না

            // Core Cart Details
            $table->unsignedInteger('quantity');
            $table->decimal('price_at_time', 10, 2); // পণ্যটি যোগ করার সময় প্রতি ইউনিটের মূল্য
            $table->string('session_id',190)->nullable(); // গেস্ট ইউজারদের জন্য
            $table->json('options')->nullable(); // প্রোডাক্ট অপশন (যেমন: সাইজ, রঙ, ডিজাইন)

            // Status
            $table->enum('status', ['pending', 'ordered', 'abandoned'])->default('pending');
            // 'cancelled' স্ট্যাটাস কার্টের জন্য খুব একটা প্রয়োজন নেই, ইউজার আইটেম ডিলিট করে দিতে পারে।

            // --- মিসিং কলামটি যোগ করা হলো ---
            $table->json('price_breakdown')->nullable(); // মূল্য বিভাজন (JSON), যেমন: ['subtotal' => 500, 'shipping' => 50]

            $table->timestamps();

            // Indexes for better performance
            $table->index(['user_id', 'status']);
            $table->index(['session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carts');
    }
};
