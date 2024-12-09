<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('equipment_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('restaurants');
            $table->string('maintenance_type'); // routine, repair, inspection
            $table->date('maintenance_date');
            $table->text('work_performed');
            $table->decimal('cost', 12, 2)->nullable();
            $table->foreignId('performed_by')->constrained('employees');
            $table->date('next_maintenance_due')->nullable();
            $table->json('parts_replaced')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipment_maintenance_records');
    }
};
