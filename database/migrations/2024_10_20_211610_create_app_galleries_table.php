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
        Schema::create('app_galleries', function (Blueprint $table) {
            $table->id();

            // Project relation
            $table->unsignedBigInteger('app_project_id')->nullable();
            $table->foreign('app_project_id')->references('id')->on('app_projects')->onDelete('cascade');

            // Uploader (Employee) relation
            $table->unsignedBigInteger('uploader_id');
            $table->foreign('uploader_id')->references('id')->on('employees')->onDelete('cascade');

            // Project relation
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');


            // Media fields (only one should be filled)
            $table->string('photo_path')->nullable(); // Store the path of the photo
            $table->string('video_path')->nullable(); // Store the path of the video
            $table->softDeletes(); // Add soft deletes column
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
        Schema::dropIfExists('app_galleries');
    }
};
