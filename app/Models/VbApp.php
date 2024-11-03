<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VbApp extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'price_per_user', 'initial_fee'];

    public function appSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppSubscription::class);
    }

    public function appConfigurations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppConfiguration::class);
    }

    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
