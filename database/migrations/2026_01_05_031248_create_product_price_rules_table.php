<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_price_rules', function (Blueprint $table) {
            $table->id();

            // null = global rule (all products)
            $table->string('product_id')->nullable();

            // discount / price_add
            $table->enum('type', ['discount', 'price_add']);

            // flat / percentage
            $table->enum('value_type', ['flat', 'percentage']);

            $table->decimal('value', 10, 2);

            $table->boolean('active')->default(true);

            // optional validity
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_rules');
    }
};
