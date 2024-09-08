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
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hygiene_check_id');
            $table->string('item');
            // add log notes field
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->foreign('hygiene_check_id')->references('id')->on('hygiene_checks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('checklist_items');
    }
};
