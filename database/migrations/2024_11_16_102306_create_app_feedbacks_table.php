<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('app_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->foreignId('client_id')->nullable()->constrained('app_clients');
            $table->foreignId('project_id')->nullable()->constrained('app_projects');
            $table->integer('rating');
            $table->text('comment')->nullable();
            $table->string('type'); // equipment_service, project, general
            $table->text('admin_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_feedbacks');
    }
};
