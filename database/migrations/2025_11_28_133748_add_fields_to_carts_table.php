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
        Schema::table('carts', function (Blueprint $table) {
            $table->json('shippings')->nullable()->after('price_breakdown');
            $table->json('turnarounds')->nullable()->after('shippings');
            $table->json('delivery_address')->nullable()->after('turnarounds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['shippings', 'turnarounds', 'delivery_address']);
        });
    }
};
