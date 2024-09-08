<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionalCodePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotional_code_id',
        'image_path',
    ];

    public function promotionalCode(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PromotionalCode::class);
    }
}
