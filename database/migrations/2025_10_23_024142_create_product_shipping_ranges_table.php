<?php

// database/migrations/create_shipping_ranges_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductShippingRangesTable extends Migration
{
    public function up()
    {
        Schema::create('product_shipping_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('min_quantity');
            $table->integer('max_quantity');
            $table->decimal('discount', 8, 2)->default(0);
            $table->json('shippings')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_shipping_ranges');
    }
}
