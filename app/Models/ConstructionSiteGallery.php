<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ConstructionSiteGallery extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_galleries';
    protected $fillable = [
        'venue_id', 
        'construction_site_id', 
        'uploader_id', 
        'photo_path', 
        'video_path'
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function constructionSite()
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function uploader()
    {
        return $this->belongsTo(Employee::class, 'uploader_id');
    }

    protected function photoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

    protected function videoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }
}
