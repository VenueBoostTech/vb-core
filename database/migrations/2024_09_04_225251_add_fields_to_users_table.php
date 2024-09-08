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
        Schema::table('users', function (Blueprint $table) {
            $table->string('old_platform_registration_type')->nullable();
            $table->string('gender')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('username')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_vat')->nullable();
            $table->integer('old_platform_user_id')->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'old_platform_registration_type',
                'gender',
                'profile_photo_path',
                'old_platform_user_id',
                'username',
                'company_name',
                'company_vat',
                'status'
            ]);
            $table->dropSoftDeletes();
        });
    }
};
