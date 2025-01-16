<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class ConstructionSiteNotice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_notices';
    protected $fillable = [
        'venue_id', 
        'construction_site_id', 
        'title', 
        'description', 
        'type', 
        'attachment'
    ];

    public function constructionSite()
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    protected function attachment(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }
}
