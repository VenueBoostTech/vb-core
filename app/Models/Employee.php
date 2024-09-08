<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use hasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'email',
        'role_id',
        'manager_id',
        'owner_id',
        'salary',
        'salary_frequency',
        'user_id',
        'hire_date',
        'restaurant_id',
        'frequency_number',
        'frequency_unit',
        'custom_role'
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function owner()
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function ownerEmployees()
    {
        return $this->hasMany(Employee::class, 'owner_id', 'id');
    }

    public function hasPermission(Permission $permission)
    {
        return $this->hasAnyRole($permission->roles);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function performances()
    {
        return $this->hasMany(Performance::class);
    }

    public function hasAnyRole($roles)
    {
        if (is_array($roles) || is_object($roles)) {
            return !! $roles->intersect($this->roles)->count();
        }
        return $this->roles->contains('name', $roles);
    }

    public function salaryHistories()
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isManager()
    {
        return $this->role_id == 1;
    }

    public function isOwner()
    {
        return $this->role_id == 2;
    }
}
