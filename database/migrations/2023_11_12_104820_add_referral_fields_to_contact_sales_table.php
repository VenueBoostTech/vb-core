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

            $table->string('referral_code')->nullable();
            $table->unsignedBigInteger('referer_id')->nullable();
            $table->enum('referral_status', ['pending', 'started', 'registered'])->nullable();

            $table->foreign('referer_id')->references('id')->on('restaurants')->onDelete('cascade');

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
            $table->dropColumn('referral_code');
            $table->dropColumn('referer_id');
            $table->dropColumn('referral_status');
            // drop foreign key
            $table->dropForeign('contact_sales_referer_id_foreign');
        });
    }
};
