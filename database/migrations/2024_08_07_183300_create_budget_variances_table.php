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
        Schema::create('budget_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->onDelete('cascade');
            $table->string('category');
            $table->decimal('budgeted_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2);
            $table->decimal('variance', 15, 2);
            $table->decimal('variance_percentage', 8, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('calculated');
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
        Schema::dropIfExists('budget_variances');
    }
};
