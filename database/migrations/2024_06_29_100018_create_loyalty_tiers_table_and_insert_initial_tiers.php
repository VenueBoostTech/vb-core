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
        // Insert initial loyalty tiers
        DB::table('loyalty_tiers')->insert([
            [
                'id' => 1,
                'name' => 'Bronze Tier',
                'min_stays' => 0,
                'max_stays' => 4,
                'period_up' => 12,
                'period_down' => 6,
                'discount' => 0,
                'free_breakfast' => false,
                'free_room_upgrade' => false,
                'priority_support' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Silver Tier',
                'min_stays' => 5,
                'max_stays' => 9,
                'period_up' => 12,
                'period_down' => 6,
                'discount' => 0.10,
                'free_breakfast' => true,
                'free_room_upgrade' => false,
                'priority_support' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Gold Tier',
                'min_stays' => 10,
                'max_stays' => 19,
                'period_up' => 12,
                'period_down' => 6,
                'discount' => 0.15,
                'free_breakfast' => true,
                'free_room_upgrade' => true,
                'priority_support' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Platinum Tier',
                'min_stays' => 20,
                'max_stays' => 30,
                'period_up' => 12,
                'period_down' => 6,
                'discount' => 0.20,
                'free_breakfast' => true,
                'free_room_upgrade' => true,
                'priority_support' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
