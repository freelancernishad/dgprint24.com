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
        Schema::table('support_tickets', function (Blueprint $table) {
            // Make existing columns nullable
             $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('subject')->nullable()->change();
            $table->text('message')->nullable()->change();
            $table->enum('status', ['open', 'closed', 'pending','replay'])->default('open')->change();
            $table->enum('priority', ['low', 'medium', 'high'])->nullable()->change();
            $table->string('attachment')->nullable()->change();

       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // Revert columns to not nullable if needed
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->string('subject')->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
            $table->enum('status', ['open', 'closed', 'pending','replay'])->default('open')->change();
            $table->enum('priority', ['low', 'medium', 'high'])->nullable(false)->change();
            $table->string('attachment')->nullable(false)->change();

 
        });
    }
};
