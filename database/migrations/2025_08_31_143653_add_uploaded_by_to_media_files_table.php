<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUploadedByToMediaFilesTable extends Migration
{
    public function up()
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->after('original_url');
            $table->unsignedBigInteger('uploaded_by_admin_id')->nullable()->after('uploaded_by_user_id');

            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('uploaded_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->dropForeign(['uploaded_by_admin_id']);
            $table->dropColumn(['uploaded_by_user_id', 'uploaded_by_admin_id']);
        });
    }
}
