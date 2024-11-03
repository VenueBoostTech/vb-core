<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLanguageFieldsToBlogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->text('title_es')->nullable();
            $table->text('title_fr')->nullable();
            $table->text('title_it')->nullable();
            $table->text('title_de')->nullable();
            $table->text('title_pt')->nullable();
            $table->longText('body_es')->nullable();
            $table->longText('body_fr')->nullable();
            $table->longText('body_it')->nullable();
            $table->longText('body_de')->nullable();
            $table->longText('body_pt')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn([
                'title_es', 'title_fr', 'title_it', 'title_de', 'title_pt',
                'body_es', 'body_fr', 'body_it', 'body_de', 'body_pt'
            ]);
        });
    }
}
