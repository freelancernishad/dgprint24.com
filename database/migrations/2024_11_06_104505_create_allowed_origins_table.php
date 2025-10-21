<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAllowedOriginsTable extends Migration
{
    public function up()
    {
        Schema::create('allowed_origins', function (Blueprint $table) {
            $table->id();
            $table->string('origin_url')->unique(); // Store origin URL
            $table->timestamps(); // Created_at and updated_at timestamps
        });


        // Insert default record
        DB::table('allowed_origins')->insert([
            'origin_url' => 'postman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    public function down()
    {
        Schema::dropIfExists('allowed_origins');
    }
}
