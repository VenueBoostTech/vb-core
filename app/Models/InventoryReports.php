<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class InventoryReports extends Model
{
    use HasFactory;
    protected $fillable = [
        'period',
        'restaurant_id',
        'creator_user_id',
        'pdf_data',
        'pdf_url'
    ];

    protected $hidden = [
        'pdf_data',
    ];
    protected $casts = [
        'pdf_data' => 'json',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Change Image path to temporary url.
     */
    protected function pdfUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value != null ? Storage::disk('s3')->temporaryUrl($value, '+5 minutes') : null,
        );
    }

}
