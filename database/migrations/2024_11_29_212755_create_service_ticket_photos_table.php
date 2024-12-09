<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_ticket_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_ticket_id')->constrained()->onDelete('cascade');
            $table->string('photo_path');
            $table->string('photo_type'); // before, after, during, issue
            $table->text('description')->nullable();
            $table->string('taken_by')->nullable();
            $table->dateTime('taken_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_ticket_photos');
    }
};
