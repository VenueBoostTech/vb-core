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

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->string('stripe_id')->nullable();
//            $table->string('description')->nullable();
            $table->string('unit_label')->nullable();

            // add is custom field
            $table->boolean('is_custom')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn('stripe_id');
//            $table->dropColumn('description');
            $table->dropColumn('unit_label');
            $table->dropColumn('is_custom');
        });
    }
};
