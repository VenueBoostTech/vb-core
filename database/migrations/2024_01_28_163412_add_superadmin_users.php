<?php

use App\Models\HighLevelRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        // Find the role ID for 'Superadmin' by querying the 'HighLevelRole' model
        $superadminRole = HighLevelRole::where('name', 'Superadmin')->first();

        // Check if the 'Superadmin' role exists
        if ($superadminRole) {
            // Create two 'Superadmin' users
            DB::table('users')->insert([
                [
                    'name' => 'Superadmin Primary',
                    'email' => 'development+superadminprimary@venueboost.io',
                    'password' => Hash::make('D3-suX24!--GG-vb'), // Set a secure password
                    'role_id' => $superadminRole->id,
                    'country_code' => 'US',
                ],
                [
                    'name' => 'Superadmin Secondary',
                    'email' => 'development+superadminsecondary@venueboost.io',
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
        //
    }
};
