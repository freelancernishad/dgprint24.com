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
        Schema::create('product_dimension_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('minwidth', 8, 2); // দশমিক স্থানসহ
            $table->decimal('maxwidth', 8, 2);
            $table->decimal('minheight', 8, 2);
            $table->decimal('maxheight', 8, 2);
            $table->decimal('basePricePerSqFt', 10, 2); // মূল্যের জন্য বেশি দশমিক স্থান
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_dimension_pricings');
    }
};
