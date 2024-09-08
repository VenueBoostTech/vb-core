<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VbStoreAttributeType extends Model
{
    use HasFactory;

    protected $table = 'vb_store_attributes_types';

    protected $fillable = ['type', 'description'];

    public function attributes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VbStoreAttribute::class, 'type_id');
    }
}
