<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionSiteTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_team';

    protected $fillable = [
        'construction_site_id',
        'team_id'
    ];

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}