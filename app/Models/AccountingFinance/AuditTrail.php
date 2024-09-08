<?php

namespace App\Models\AccountingFinance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'action',
        'description',
        'ip_address',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
