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


        Schema::table('affiliate_programs', function (Blueprint $table) {
            $table->string('ap_unique')->unique();
            $table->string('preferred_method')->nullable();
        });


        // Insert the provided data
        DB::table('affiliate_programs')->insert([
                [
                    'name' => 'Content focused',
                    'description' => 'Content-Focused Affiliates',
                    'ap_unique' => 'AP-CF',
                    'commission_fee' => 0.00,
                ],
                [
                    'name' => 'Immediate impact',
                    'description' => 'Immediate Impact Affiliates',
                    'ap_unique' => 'AP-II',
                    'commission_fee' => 0.00,
                ],
                [
                    'name' => 'Business and niche',
                    'description' => 'Business and Niche Partners',
                    'ap_unique' => 'AP-BN-1',
                    'commission_fee' => 0.00,
                ],
                [
                    'name' => 'Business and niche',
                    'description' => 'Business and Niche Partners',
                    'ap_unique' => 'AP-BN-2',
                    'commission_fee' => 0.00,
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
        Schema::table('affiliate_programs', function (Blueprint $table) {
            $table->dropColumn('ap_unique');
            $table->dropColumn('preferred_method');
        });
    }
};
