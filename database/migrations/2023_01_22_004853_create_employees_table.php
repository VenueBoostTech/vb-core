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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->decimal('salary', 8, 2)->default(0);
            $table->enum('salary_frequency', ['daily', 'weekly', 'bi-weekly', 'monthly'])->default('monthly');
            $table->timestamps();


            $table->foreign('role_id')->references('id')->on('roles');
            $table->foreign('manager_id')->references('id')->on('employees');
            $table->foreign('owner_id')->references('id')->on('employees');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
