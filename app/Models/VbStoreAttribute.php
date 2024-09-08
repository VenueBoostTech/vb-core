<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VbStoreAttribute extends Model
{
    use HasFactory;


    protected $fillable = ['type_id', 'attr_name', 'attr_url', 'attr_description', 'order_id'];

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VbStoreAttributeType::class, 'type_id');
    }

    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VbStoreAttributeOption::class, 'attribute_id');
    }
}
