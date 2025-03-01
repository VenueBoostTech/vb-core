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
        Schema::create('vt_streams', function (Blueprint $table) {
            $table->id();
            $table->string('stream_id')->unique();
            $table->string('name');
            $table->string('url');
            $table->foreignId('device_id')->constrained('vt_devices')->onDelete('cascade');
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
        Schema::dropIfExists('vt_streams');
    }
};
