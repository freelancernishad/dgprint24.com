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
        Schema::create('price_configuration_shippings', function (Blueprint $table) {
            $table->string('id')->primary(); // Primary key is a string
            $table->foreignId('price_config_id')->constrained('price_configurations')->onDelete('cascade');
            $table->string('shippingLabel');
            $table->integer('shippingValue');
            $table->decimal('price', 10, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_configuration_shippings');
    }
};
