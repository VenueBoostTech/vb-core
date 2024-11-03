<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('status', [
                'active',
                'inactive',
                'on-break',
                'off-duty',
                'on-leave',      // For employees on vacation or other types of leave
                'suspended',     // For temporary suspension of employment
                'probation',     // For employees under probationary period
                'terminated'     // For employees whose employment has been terminated
            ])->default('active')->after('email');

            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropSoftDeletes();
        });
    }
};
