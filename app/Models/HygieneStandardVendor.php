<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HygieneStandardVendor extends Model
{
    use HasFactory;

    // Define table name
    protected $table = 'hygiene_standards_vendors';
    protected $fillable = [
        'venue_id', 'name', 'type', 'contact_name', 'contact_email',
        'contact_phone', 'address', 'hygiene_rating', 'compliance_certified',
        'certification_details', 'notes'
    ];

    // Define relationship to the Venue (Restaurant)
    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class, 'venue_id');
    }
}
