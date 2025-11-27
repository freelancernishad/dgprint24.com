<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('support_tickets', function (Blueprint $table) {
        $table->string('job_id')->nullable();
        $table->string('login_email')->nullable();
        $table->string('company_name')->nullable();
        $table->string('contact_name')->nullable();
        $table->string('contact_telephone')->nullable();
        $table->string('contact_email')->nullable();
        $table->string('problem_category')->nullable();
        $table->boolean('request_reprint')->default(false);
        $table->text('problem_description')->nullable();
    });
}

public function down()
{
    Schema::table('support_tickets', function (Blueprint $table) {
        $table->dropColumn([
            'job_id',
            'login_email',
            'company_name',
            'contact_name',
            'contact_telephone',
            'contact_email',
            'problem_category',
            'request_reprint',
            'problem_description',
        ]);
    });
}

};
