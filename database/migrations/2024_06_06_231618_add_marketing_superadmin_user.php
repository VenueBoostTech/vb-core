<?php

use App\Models\HighLevelRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Find the role ID for 'Superadmin' by querying the 'HighLevelRole' model
        $superadminRole = HighLevelRole::where('name', 'Superadmin')->first();

        // Check if the 'Superadmin' role exists
        if ($superadminRole) {
            // Create another 'Superadmin' user
            DB::table('users')->insert([
                [
                    'name' => 'Superadmin Secondary',
                    'email' => 'klea+marketing@venueboost.io',
                    'password' => Hash::make('D3-suX24!--RD-vb'), // Set a secure password
                    'role_id' => $superadminRole->id,
                    'country_code' => 'US',
                ],
            ]);
        } else {
            // Handle the case where 'Superadmin' role doesn't exist
            // You can add appropriate error handling logic here
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Find the 'Superadmin Secondary' user and delete it
        DB::table('users')->where('email', 'klea+marketing@venueboost.io')->delete();
    }
};
