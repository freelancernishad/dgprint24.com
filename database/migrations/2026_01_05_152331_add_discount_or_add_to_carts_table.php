<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {

            // discount = minus, add = plus
            $table->decimal('discount_or_add', 10, 2)
                  ->default(0)
                  ->after('price_at_time'); // চাইলে position change করো
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('discount_or_add');
        });
    }
};
