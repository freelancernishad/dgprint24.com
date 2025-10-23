<?php

// database/migrations/create_turnaround_ranges_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductTurnaroundRangesTable extends Migration
{
    public function up()
    {
        Schema::create('product_turnaround_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('min_quantity');
            $table->integer('max_quantity');
            $table->decimal('discount', 8, 2)->default(0);
            $table->json('turnarounds')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_turnaround_ranges');
    }
}
