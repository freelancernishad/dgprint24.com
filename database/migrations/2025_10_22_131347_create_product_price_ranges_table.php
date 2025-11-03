<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable(); // 'and above' এর জন্য null থাকবে
            $table->decimal('price_per_sq_ft', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_ranges');
    }
};
