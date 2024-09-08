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
        Schema::table('marketing_waitlists', function (Blueprint $table) {
            $table->boolean('first_email_sent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketing_waitlists', function (Blueprint $table) {
            // drop the field first_email_sent
            $table->dropColumn('first_email_sent');
        });
    }
};
