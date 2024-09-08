<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id'];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FacilityCategory::class, 'category_id', 'id');
    }

    public function rentalUnits(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(RentalUnit::class, 'rental_unit_facility', 'facility_id', 'rental_unit_id');
    }

    // getFacilitiesGroupedByCategory
    public static function getFacilitiesGroupedByCategory(): array
    {
        $facilities = Facility::with('category')->get();
        $facilitiesGroupedByCategory = [];
        foreach ($facilities as $facility) {
            $facilitiesGroupedByCategory[$facility->category->name][] = $facility;
        }
        return $facilitiesGroupedByCategory;
    }

    // getFacilitiesGroupedByCategory for Rental Unit
    public static function getFacilitiesGroupedByCategoryByRentalUnitId($rentalUnitId): array
    {
        $facilities = Facility::with('category')->whereHas('rentalUnits', function ($query) use ($rentalUnitId) {
            $query->where('rental_unit_id', $rentalUnitId);
        })->get();
        $facilitiesGroupedByCategory = [];
        foreach ($facilities as $facility) {
            $facilitiesGroupedByCategory[$facility->category->name][] = $facility;
        }
        return $facilitiesGroupedByCategory;
    }
}
