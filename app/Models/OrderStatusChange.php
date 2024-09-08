<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusChange extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'new_status', 'changed_at'];

    protected $table = 'order_status_changes';

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
