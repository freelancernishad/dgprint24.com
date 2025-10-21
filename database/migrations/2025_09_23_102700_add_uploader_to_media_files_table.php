<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            // Add polymorphic uploader columns
            $table->unsignedBigInteger('uploader_id')->nullable()->after('original_url');
            $table->string('uploader_type')->nullable()->after('uploader_id');

            // Optional: remove old uploader columns if no longer needed
            // $table->dropColumn(['uploaded_by_user_id', 'uploaded_by_admin_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn(['uploader_id', 'uploader_type']);

            // Optional: restore old uploader columns
            // $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->after('original_url');
            // $table->unsignedBigInteger('uploaded_by_admin_id')->nullable()->after('uploaded_by_user_id');
        });
    }
};
