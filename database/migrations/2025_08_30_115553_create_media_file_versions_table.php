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
        Schema::create('media_file_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_file_id');
            $table->string('label'); // original, thumbnail, 300x300
            $table->string('url');
            $table->bigInteger('size'); // bytes
            $table->string('size_type'); // "1.2 MB", "450 KB"
            $table->string('type'); // mime type
            $table->string('dimensions'); // "300x300"
            $table->timestamps();

            $table->foreign('media_file_id')->references('id')->on('media_files')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_file_versions');
    }
};
