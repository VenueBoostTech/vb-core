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
        Schema::create('promotional_codes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('usage');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->enum('for', ['food', 'entertainment', 'accommodation', 'retail', 'all'])->default('all');
            $table->string('code')->unique();
            $table->unsignedBigInteger('type');
            $table->foreign('type')->references('id')->on('promo_code_types')->onDelete('cascade');
            $table->string('banner')->nullable();
            $table->string('created_by')->default('vb_backend');
            $table->integer('creation_user_id')->nullable();
            $table->enum('campaign', ['pre-relaunch', 'launch'])->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('promotional_codes');
    }
};
