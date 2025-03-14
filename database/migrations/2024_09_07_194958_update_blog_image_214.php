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
        // Update the image field for the blog with id = 214
        DB::table('blogs')
            ->where('id', 216)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/09/Untitled-design-36.png']);

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
