<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->string('name_al')->nullable()->after('name');
            $table->text('description_al')->nullable()->after('description');
            $table->integer('bybest_id')->nullable();
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->string('title_al')->nullable()->after('title');
            $table->text('content_al')->nullable()->after('content');
            $table->integer('bybest_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropColumn([
                'name_al',
                'description_al',
                'bybest_id'
            ]);
        });

        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn([
                'title_al',
                'content_al',
                'bybest_id'
            ]);
        });
    }
};
