<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 🔹 Drop foreign key if it exists (MySQL-safe)
        DB::statement("
            ALTER TABLE support_tickets
            DROP FOREIGN KEY IF EXISTS support_tickets_user_id_foreign
        ");

        Schema::table('support_tickets', function (Blueprint $table) {
            // 🔹 Change column to string
            $table->string('user_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }
};
