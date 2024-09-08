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
        Schema::table('contact_sales', function (Blueprint $table) {
            $table->string('affiliate_code')->nullable();
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->enum('affiliate_status', ['pending', 'started', 'registered'])->nullable();

            $table->foreign('affiliate_id')->references('id')->on('affiliates')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contact_sales', function (Blueprint $table) {
            $table->dropColumn('affiliate_code');
            $table->dropColumn('affiliate_id');
            $table->dropColumn('affiliate_status');
        });
    }
};
