<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vt_devices', function (Blueprint $table) {
            DB::statement("ALTER TABLE vt_devices MODIFY COLUMN brand ENUM('UNV', 'Hikvision', 'Other', 'Dahua', 'Axis', 'Hanwha', 'Bosch', 'Uniview', 'Avigilon', 'Pelco', 'Vivotek', 'Panasonic', 'Honeywell', 'CP Plus', 'Tiandy', 'TVT', 'ZKTeco', 'Mobotix', 'Reolink', 'Lorex', 'Swann', 'Custom')");

            $table->string('custom_brand')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('vt_devices', function (Blueprint $table) {
            DB::statement("ALTER TABLE vt_devices MODIFY COLUMN brand ENUM('UNV', 'Hikvision', 'Other')");
            $table->dropColumn('custom_brand');
        });
    }
};
