<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDeviceToken extends Model
{
    use HasFactory;

    protected $fillable = ['employee_device_id', 'token'];

    public function device()
    {
        return $this->belongsTo(EmployeeDevice::class, 'employee_device_id');
    }
}
