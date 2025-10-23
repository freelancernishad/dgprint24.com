<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('job_sample_price', 10, 2)->default(0)->after('base_price');
            $table->decimal('digital_proof_price', 10, 2)->default(0)->after('job_sample_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['job_sample_price', 'digital_proof_price']);
        });
    }
};
