<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscribedEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'unsubscribed',
        'lead_id',
        'contact_form_id',
        'contact_sales_id',
    ];

    public function lead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PotentialVenueLead::class);
    }

    public function contactForm(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContactFormSubmission::class);
    }

    public function contactSales(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContactSales::class);
    }

}
