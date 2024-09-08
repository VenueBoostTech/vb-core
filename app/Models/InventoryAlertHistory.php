<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAlertHistory extends Model
{
    use HasFactory;

    protected $table = 'inventory_alert_history';

    protected $fillable = [
            'inventory_alert_id',
            'stock_quantity_at_alert',
            'alert_triggered_at',
            'is_resolved',
            'resolved_at',
        ];

    public function inventoryAlert(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryAlert::class);
    }
}
