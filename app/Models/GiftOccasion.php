<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftOccasion extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function suggestions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GiftSuggestion::class);
    }
}
