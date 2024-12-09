<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {

        Schema::dropIfExists('workflows');
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->morphs('processable');  // For any entity that needs workflow management
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');  // safety, quality, maintenance, etc.
            $table->json('steps');
            $table->string('status');
            $table->string('priority');
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workflows');
    }
};
