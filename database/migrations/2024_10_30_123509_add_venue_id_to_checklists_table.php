<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            // Make existing columns nullable
            $table->string('item')->nullable()->change();
            $table->unsignedBigInteger('hygiene_check_id')->nullable()->change();

            // Add new columns
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->foreignId('completed_by')->nullable()->constrained('employees');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('checklist_id')->nullable()->constrained()->onDelete('cascade');
        });

        // Update checklists table
        Schema::table('checklists', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('app_projects')->onDelete('cascade');
            $table->foreignId('venue_id')->nullable()->constrained('restaurants')->onDelete('cascade');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->dropColumn('items'); // Remove json column
        });
    }

    public function down()
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            // Reverse new columns
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['checklist_id']);

            $table->dropColumn([
                'name',
                'description',
                'due_date',
                'priority',
                'status',
                'assigned_to',
                'completed_by',
                'completed_at',
                'checklist_id'
            ]);

            // Make original columns required again
            $table->string('item')->nullable(false)->change();
            $table->unsignedBigInteger('hygiene_check_id')->nullable(false)->change();
        });

        Schema::table('checklists', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['venue_id']);

            $table->dropColumn([
                'description',
                'project_id',
                'venue_id',
                'start_date',
                'end_date'
            ]);

            $table->json('items'); // Add back json column
        });
    }
};
