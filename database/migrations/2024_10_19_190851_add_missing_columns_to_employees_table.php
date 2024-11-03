<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'personal_phone')) {
                $table->string('personal_phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('employees', 'personal_email')) {
                $table->string('personal_email')->nullable()->after('email');
            }
            if (!Schema::hasColumn('employees', 'company_phone')) {
                $table->string('company_phone')->nullable()->after('personal_phone');
            }
            if (!Schema::hasColumn('employees', 'company_email')) {
                $table->string('company_email')->nullable()->after('email');
            }

        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'personal_phone',
                'personal_email',
                'company_phone',
                'company_email'
            ]);
        });
    }
};
