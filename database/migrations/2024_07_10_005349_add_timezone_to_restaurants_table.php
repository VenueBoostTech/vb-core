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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('timezone')->default('UTC');
        });
        // Update the specific restaurant with id = 23
        DB::table('restaurants')
            ->where('id', 23)
            ->update(['timezone' => 'Europe/Tirane']);
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('timezone')->default('UTC');
        });
    }
};
