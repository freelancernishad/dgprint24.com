<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('runsize');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->json('options'); // অপশন কম্বিনেশনের জন্য
            $table->timestamps();

            // দ্রুত খোঁজার জন্য ইনডেক্স
            $table->index(['product_id', 'runsize']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_configurations');
    }
};
