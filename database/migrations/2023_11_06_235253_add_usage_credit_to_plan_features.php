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
        Schema::table('plan_features', function (Blueprint $table) {
            $table->integer('usage_credit');
            $table->enum('whitelabel_access', ['vb_related', 'own'])->nullable();
            $table->boolean('allow_vr_ar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plan_features', function (Blueprint $table) {
            $table->dropColumn('usage_credit');
            $table->dropColumn('whitelabel_access');
            $table->dropColumn('allow_vr_ar');
        });
    }
};
