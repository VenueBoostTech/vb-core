<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vt_devices', function (Blueprint $table) {
            $table->text('description')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('vt_devices', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
