<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDevice extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'device_type', 'device_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function tokens()
    {
        return $this->hasMany(EmployeeDeviceToken::class);
    }
}
