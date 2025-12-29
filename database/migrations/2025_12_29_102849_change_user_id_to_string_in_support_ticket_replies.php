<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        // 🔹 FK থাকলে drop করবে, না থাকলে skip করবে
        DB::statement("
            ALTER TABLE support_ticket_replies
            DROP FOREIGN KEY IF EXISTS support_ticket_replies_user_id_foreign
        ");

        // 🔹 user_id কে string করা
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->string('user_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {

            $table->unsignedBigInteger('user_id')->change();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }
};
