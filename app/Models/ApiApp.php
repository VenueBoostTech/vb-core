<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiApp extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
        'usage_count',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_apps';
}
