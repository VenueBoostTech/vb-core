<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ConstructionSite;
use App\Models\Employee;

class ConstructionSiteCheckInOut extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'construction_site_check_in_out';
    protected $fillable = ['construction_site_id', 'employee_id', 'check_in_time', 'check_out_time', 'location', 'latitude', 'longitude'];

    public function constructionSite()
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
