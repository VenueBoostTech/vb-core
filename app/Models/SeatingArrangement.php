<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeatingArrangement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'table_id', 'guest_ids', 'start_time', 'end_time', 'restaurant_id'
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function guests()
    {
        if (!is_array($this->guest_ids)) {
            return collect([]);
        }

        return Guest::whereIn('id', $this->guest_ids)->get();
    }
}
