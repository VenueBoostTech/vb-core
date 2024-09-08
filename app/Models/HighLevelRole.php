<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HighLevelRole extends Model
{
    protected $table = 'high_level_roles';

    protected $fillable = ['name'];

    use HasFactory;

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
