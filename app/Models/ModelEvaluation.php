<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelEvaluation extends Model
{
    use HasFactory;

    protected $table = 'model_evaluation';

    protected $fillable = [
        'model_name',
        'mae',
        'mse',
        'rmse',
    ];
}
