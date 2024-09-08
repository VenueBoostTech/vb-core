<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    public function templates(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Template::class, 'sendable');
    }
}
