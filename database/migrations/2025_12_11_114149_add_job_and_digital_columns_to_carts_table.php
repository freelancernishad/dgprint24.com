<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJobAndDigitalColumnsToCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('carts', function (Blueprint $table) {
            // price fields
            $table->decimal('job_sample_price', 10, 2)->nullable()->after('tax_price');
            $table->decimal('digital_proof_price', 10, 2)->nullable()->after('job_sample_price');

            // boolean flags
        $table->boolean('job_sample')->default(false)->after('digital_proof_price');
        $table->boolean('digital_proof')->default(false)->after('job_sample');
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
            $table->dropColumn(['job_sample_price', 'digital_proof_price', 'job_sample', 'digital_proof']);
        });
    }
}
