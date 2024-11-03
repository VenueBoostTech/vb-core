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
        $oldEmail = 'development+077@snapfood.io';
        $newEmail = 'staf@electral.al';

        $affected = DB::table('users')
            ->where('email', $oldEmail)
            ->update(['email' => $newEmail]);

        if ($affected === 0) {
            throw new Exception("No user found with email: {$oldEmail}");
        } elseif ($affected > 1) {
            throw new Exception("Multiple users found with email: {$oldEmail}");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $oldEmail = 'development+077@snapfood.io';
        $newEmail = 'staf@electral.al';

        DB::table('users')
            ->where('email', $newEmail)
            ->update(['email' => $oldEmail]);
    }
};
