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
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('end_user_id')->nullable();
            $table->foreign('end_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('venue_user_id')->nullable();
            $table->foreign('venue_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->foreign('venue_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->enum('status', ['active', 'archived', 'deleted'])->default('active');
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
        Schema::dropIfExists('chats');
    }
};
