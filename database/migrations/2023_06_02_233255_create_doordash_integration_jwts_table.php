<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doordash_integration_jwts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doordash_integration_id')->constrained('doordash_integrations');
            $table->text('jwt');
            $table->timestamp('expiry_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('doordash_integration_jwts');
    }
};
