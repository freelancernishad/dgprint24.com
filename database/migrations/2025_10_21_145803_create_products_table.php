<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique();
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('popular_product')->default(false);
            $table->json('product_options'); // ডাইনামিক অপশনগুলোর জন্য
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
