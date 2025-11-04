<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceConfigurationOptionsTable extends Migration
{
    public function up()
    {
        Schema::create('price_configuration_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_configuration_id')->constrained()->onDelete('cascade');
            $table->string('key', 191);
            $table->string('value', 191)->nullable();
            $table->timestamps();

            $table->index(['key', 'value']); // fast filtering
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_configuration_options');
    }
}
