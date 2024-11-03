<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSubscription extends Model
{
    use HasFactory;

    protected $fillable = ['venue_id', 'vb_app_id', 'status', 'start_date', 'end_date', 'price_per_user', 'initial_fee_paid'];

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }

    public function vbApp(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbApp::class);
    }

    public function userAppAccesses()
    {
        return $this->hasMany(UserAppAccess::class);
    }
}
