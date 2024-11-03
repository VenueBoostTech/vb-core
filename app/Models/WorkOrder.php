<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class WorkOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'app_project_id',
        'operation_manager_id',
        'venue_id',
        'description',
        'status',
        'start_date',
        'end_date',
        'completion_notes',
        'name',
        'finance_order_id',
        'priority'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
        'priority' => 'string'
    ];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppProject::class, 'app_project_id');
    }

    public function operationManager(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operation_manager_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
