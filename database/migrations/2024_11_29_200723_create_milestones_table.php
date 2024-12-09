<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('milestones');

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->morphs('trackable');  // For any entity that needs milestone tracking
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->integer('progress')->default(0);
            $table->string('status');
            $table->integer('sequence');
            $table->json('dependencies')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down()
    {
        Schema::dropIfExists('milestones');
    }
};
