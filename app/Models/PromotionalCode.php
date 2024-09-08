<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionalCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'usage', 'start', 'end', 'for', 'code', 'type', 'banner', 'created_by','creation_user_id',
        'campaign', 'category_description'
    ];

    // usage -1 means unlimited


    public function promoCodeType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PromoCodeType::class, 'type');
    }

    // has one
    public function promotionalCodePhoto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PromotionalCodePhoto::class);
    }

    public function potentialVenueLeads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PotentialVenueLead::class);
    }
}
