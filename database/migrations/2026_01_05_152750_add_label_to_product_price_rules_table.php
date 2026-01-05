<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_price_rules', function (Blueprint $table) {

            // Human readable label (Admin UI / Cart display)
            $table->string('label')
                  ->nullable()
                  ->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_rules', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
