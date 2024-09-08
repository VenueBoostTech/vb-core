<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'credit_limit',
        'current_balance',
        'last_review_date'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'last_review_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Scopes
    public function scopeOverLimit($query)
    {
        return $query->whereRaw('current_balance > credit_limit');
    }

    public function scopeNearLimit($query, $percentage = 90)
    {
        return $query->whereRaw('current_balance >= credit_limit * ?', [$percentage / 100]);
    }

    public function scopeNeedsReview($query, $daysAgo = 365)
    {
        return $query->where('last_review_date', '<=', now()->subDays($daysAgo));
    }

    // Methods
    public function getAvailableCredit()
    {
        return max($this->credit_limit - $this->current_balance, 0);
    }

    public function getCreditUtilizationPercentage()
    {
        return $this->credit_limit > 0 ? ($this->current_balance / $this->credit_limit) * 100 : 0;
    }

    public function isOverLimit()
    {
        return $this->current_balance > $this->credit_limit;
    }

    public function increaseBalance($amount)
    {
        $this->current_balance += $amount;
        $this->save();
    }

    public function decreaseBalance($amount)
    {
        $this->current_balance = max($this->current_balance - $amount, 0);
        $this->save();
    }

    public function updateCreditLimit($newLimit)
    {
        $this->credit_limit = $newLimit;
        $this->last_review_date = now();
        $this->save();
    }

    public static function getTotalOutstandingCredit()
    {
        return self::sum('current_balance');
    }

    public static function getAverageCreditUtilization()
    {
        return self::selectRaw('AVG(current_balance / credit_limit) * 100 as avg_utilization')
            ->value('avg_utilization');
    }
}
