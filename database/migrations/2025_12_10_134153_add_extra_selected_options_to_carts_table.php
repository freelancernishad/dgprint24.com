<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtraSelectedOptionsToCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carts', function (Blueprint $table) {
            // project_name থাকলে তার পরে যোগ করতে চাইলে ->after('project_name')
            // যদি আপনার DB/MySQL engine JSON সহ না করে, ব্যবহার করুন ->text('extra_selected_options')->nullable()
            $table->json('extra_selected_options')->nullable()->after('project_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('extra_selected_options');
        });
    }
}
