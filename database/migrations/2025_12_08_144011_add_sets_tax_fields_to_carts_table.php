<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up()
{
    Schema::table('carts', function (Blueprint $table) {
        $table->integer('sets')->nullable()->after('quantity');   // মোট সেট/সেট সংখ্যা
        $table->unsignedBigInteger('tax_id')->nullable()->after('sets');
        $table->decimal('tax_price', 10, 2)->nullable()->after('tax_id');

        // যদি tax_id → taxes টেবিলকে reference করতে চান:
        $table->foreign('tax_id')->references('id')->on('taxes')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('carts', function (Blueprint $table) {
        $table->dropForeign(['tax_id']);
        $table->dropColumn(['sets', 'tax_id', 'tax_price']);
    });
}

};
