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
        // Update the image field for the blog with id = 70
        DB::table('blogs')
            ->where('id', 70)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/06/blog-vb.png']);

        // Update the image field for the blog with id = 71
        DB::table('blogs')
            ->where('id', 71)
            ->update(['image' => 'https://neps.al/wp-content/uploads/neps-uploads/2024/06/blog-v2.png']);
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
