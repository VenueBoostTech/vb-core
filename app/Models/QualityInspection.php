<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityInspection extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'app_project_id', 'team_leader_id', 'venue_id', 'remarks', 'status',
        'inspection_date', 'rating', 'improvement_suggestions',  'name'
    ];

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AppProject::class);
    }

    public function teamLeader(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class, 'team_leader_id');
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
