<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'exchange_rate'
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
    ];

    // Scopes
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeOrderByExchangeRate($query, $direction = 'asc')
    {
        return $query->orderBy('exchange_rate', $direction);
    }

    // Methods
    public function updateExchangeRate($newRate)
    {
        $this->exchange_rate = $newRate;
        $this->save();
    }

    public static function convert($amount, $fromCurrency, $toCurrency)
    {
        $from = self::byCode($fromCurrency)->first();
        $to = self::byCode($toCurrency)->first();

        if (!$from || !$to) {
            throw new \Exception("Invalid currency code");
        }

        return ($amount / $from->exchange_rate) * $to->exchange_rate;
    }

    public static function getExchangeRate($fromCurrency, $toCurrency)
    {
        $from = self::byCode($fromCurrency)->first();
        $to = self::byCode($toCurrency)->first();

        if (!$from || !$to) {
            throw new \Exception("Invalid currency code");
        }

        return $to->exchange_rate / $from->exchange_rate;
    }

    public static function getStrongestCurrency()
    {
        return self::orderByExchangeRate('desc')->first();
    }

    public static function getWeakestCurrency()
    {
        return self::orderByExchangeRate('asc')->first();
    }

    public function isStrongerThan($otherCurrencyCode)
    {
        $other = self::byCode($otherCurrencyCode)->first();
        return $this->exchange_rate > $other->exchange_rate;
    }

    public function isWeakerThan($otherCurrencyCode)
    {
        $other = self::byCode($otherCurrencyCode)->first();
        return $this->exchange_rate < $other->exchange_rate;
    }
}
