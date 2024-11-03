<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectionRefreshLogsTable extends Migration
{
    public function up()
    {
        Schema::create('connection_refresh_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('calendar_connections')->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants')->onDelete('cascade');

            $table->unsignedBigInteger('rental_unit_id');$table->string('connection_type'); // Add the connection type column
            $table->enum('status', ['success', 'error']); // Status of the refresh attempt
            $table->text('message'); // Log message
            $table->timestamps(); // For created_at and updated_at

            $table->foreign('rental_unit_id')->references('id')->on('rental_units')->onDelete('cascade');

        });
    }

    public function down()
    {
        Schema::dropIfExists('connection_refresh_logs');
    }
}
