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
        Schema::create('artwork_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_template_group_id')->constrained()->onDelete('cascade');
            $table->string('side')->nullable(); // FRONT, BACK, BOTH
            $table->string('label'); // e.g., Standard Horizontal
            $table->json('options')->nullable(); // Specific variation options
            $table->json('files'); // Array of file formats: [{"format": "EPS", "url": "..."}, ...]
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artwork_templates');
    }
};
