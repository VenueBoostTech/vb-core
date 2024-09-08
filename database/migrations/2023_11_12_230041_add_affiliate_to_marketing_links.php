<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Add the new column
        Schema::table('marketing_links', function (Blueprint $table) {
            $table->string('affiliate_code')->nullable();
        });

        // Modify the 'type' enum column
        DB::statement("ALTER TABLE marketing_links MODIFY COLUMN type ENUM('referral', 'other', 'affiliate')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marketing_links', function (Blueprint $table) {
            $table->dropColumn('affiliate_code');
        });

        // Revert the 'type' enum column to its original state
        DB::statement("ALTER TABLE marketing_links MODIFY COLUMN type ENUM('referral', 'other')");
    }
};
