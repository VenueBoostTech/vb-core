<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class ConstructionSiteIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_issues';
    protected $fillable = [
        'venue_id',
        'construction_site_id', 
        'assigned_to', 
        'title',
        'location',
        'description',
        'priority',
        'status',
        'image'
    ];

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }
    
}
