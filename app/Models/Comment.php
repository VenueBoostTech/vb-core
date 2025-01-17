<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'employee_id',
        'construction_site_issue_id',
        'parent_id',
        'comment',
        'image_path',
        'venue_id'
    ];

    protected $with = ['employee', 'replies'];

    protected $appends = ['time_ago'];

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function constructionSiteIssue(): BelongsTo
    {
        return $this->belongsTo(ConstructionSiteIssue::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(AppProject::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}

