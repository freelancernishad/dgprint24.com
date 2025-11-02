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
        Schema::table('categories', function (Blueprint $table) {
            // 'active' কলামের পরে 'show_in_navbar' কলামটি যোগ করুন
            // ডিফল্ট মান হিসেবে false সেট করুন
            $table->boolean('show_in_navbar')->default(false)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            //
                     $table->dropColumn('show_in_navbar');
        });
    }
};
