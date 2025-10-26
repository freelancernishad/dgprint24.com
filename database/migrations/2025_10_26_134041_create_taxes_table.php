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
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('country');
            $table->string('state')->nullable(); // কিছু কিছু দেশে স্টেট থাকে না, তাই nullable
            $table->decimal('price', 8, 2); // ট্যাক্সের হার, যেমন: 8.25
            $table->timestamps();

            // একই দেশ এবং স্টেটের জন্য ডুপ্লিকেট ট্যাক্স তৈরি হওয়া থেকে বিরত রাখতে
            $table->unique(['country', 'state']);

            // দ্রুত খোঁজার জন্য ইনডেক্স
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
