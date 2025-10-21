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
        Schema::table('products', function (Blueprint $table) {
            // product_options কলামের নাম পরিবর্তন করে dynamicOptions করা হচ্ছে
            $table->renameColumn('product_options', 'dynamicOptions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // রোলব্যাক করার সময় আবার dynamicOptions থেকে product_options করা হবে
            $table->renameColumn('dynamicOptions', 'product_options');
        });
    }
};
