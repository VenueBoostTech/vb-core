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

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'is_active')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'sections')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('sections');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'section_1_new_ul_list')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('section_1_new_ul_list');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'slug')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }


        // drop al column if exists
        if (Schema::hasColumn('blogs', 'slug_related')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('slug_related');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'author_avatar')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('author_avatar');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'author_name')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('author_name');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'author_designation')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('author_designation');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'read_time')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('read_time');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'has_tags')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('has_tags');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'detail_image')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('detail_image');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'detail_image_2')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('detail_image_2');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'detail_image_3')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('detail_image_3');
            });
        }

        // drop al column if exists
        if (Schema::hasColumn('blogs', 'detail_image_4')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('detail_image_4');
            });
        }


        Schema::table('blogs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->json('sections')->nullable();
            $table->json('section_1_new_ul_list')->nullable();
            $table->string('slug');
            $table->string('slug_related');
            $table->string('author_avatar')->nullable();
            $table->string('author_name');
            $table->string('author_designation');
            $table->integer('read_time');
            $table->boolean('has_tags')->default(false);
            $table->string('detail_image')->nullable();
            $table->string('detail_image_2')->nullable();
            $table->string('detail_image_3')->nullable();
            $table->string('detail_image_4')->nullable();
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
                'is_active',
                'sections',
                'section_1_new_ul_list',
                'slug',
                'slug_related',
                'author_avatar',
                'author_name',
                'author_designation',
                'read_time',
                'has_tags',
                'detail_image',
                'detail_image_2',
                'detail_image_3',
                'detail_image_4',
            ]);
        });
    }
};
