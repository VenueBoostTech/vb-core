<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Collection extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'name_al', 'description', 'description_al', 'logo_path', 'venue_id', 'slug', 'bybest_id', 'created_at', 'updated_at', 'deleted_at'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Change Image path to temporary url.
     */
    protected function LogoPath(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }
}
