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
        Schema::create('price_configuration_turnarounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_configuration_id')->constrained('price_configurations')->onDelete('cascade');
            $table->string('turnaroundLabel');
            $table->integer('turnaroundValue');
            $table->decimal('price', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_configuration_turnarounds');
    }
};
