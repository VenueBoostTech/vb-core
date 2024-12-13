<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UpdateUserEmailAndPassword1234 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('users')
            ->where('email', 'march@staffluenttechsolutions.com')
            ->update([
                'email' => 'ivitase@staffluent.xyz',
                'password' => Hash::make('12345'),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('users')
            ->where('email', 'ivitase@staffluent.xyz')
            ->update([
                'email' => 'march@staffluenttechsolutions.com',
                'password' => Hash::make('securepassword1234'),
            ]);
    }
}
