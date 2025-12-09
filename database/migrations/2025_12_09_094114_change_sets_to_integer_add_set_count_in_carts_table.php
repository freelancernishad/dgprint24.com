<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Change sets column to INT (nullable)
        DB::statement("ALTER TABLE `carts` MODIFY COLUMN `sets` JSON NULL");

        // Add new set_count column
        Schema::table('carts', function (Blueprint $table) {
            $table->integer('set_count')->default(0)->after('sets');
        });
    }

    public function down()
    {
        // Remove set_count column
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('set_count');
        });

        // Restore sets back to JSON
        DB::statement("ALTER TABLE `carts` MODIFY COLUMN `sets` JSON NULL");
    }
};
