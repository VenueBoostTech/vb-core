<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::table('users')
        //     ->where('email', 'griseld.gerveni+1@venueboost.io')
        //     ->update([
        //         'password' => Hash::make('Test12345!'),
        //         'email_verified_at' => now()
        //     ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We can't reverse the password change as we don't know the original password.
        // We'll only revert the email_verified_at change.
        DB::table('users')
            ->where('email', 'griseld.gerveni+1@venueboost.io')
            ->update(['email_verified_at' => null]);
    }
};
