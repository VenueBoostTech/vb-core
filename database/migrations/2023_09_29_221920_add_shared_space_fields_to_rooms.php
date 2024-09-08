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
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'shared_space')) {
                $table->boolean('shared_space')->default(false);
            }

            if (!Schema::hasColumn('rooms', 'has_private_bathroom')) {
                $table->boolean('has_private_bathroom')->default(false);
            }

            if (!Schema::hasColumn('rooms', 'shared_space_with')) {
                $table->json('shared_space_with')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('shared_space');
            $table->dropColumn('shared_space_with');
            $table->dropColumn('has_private_bathroom');
        });
    }
};
