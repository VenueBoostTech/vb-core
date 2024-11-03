<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppConfigurationsTable extends Migration
{
    public function up()
    {
        Schema::create('app_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');
            $table->foreignId('vb_app_id')->constrained('vb_apps')->onDelete('cascade');
            $table->string('app_name');
            $table->string('main_color', 7);
            $table->string('button_color', 7);
            $table->text('logo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_configurations');
    }
}
