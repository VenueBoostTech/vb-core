<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tables';

    protected $dates = ['deleted_at'];


    protected $fillable = [
       'number', 'name', 'size', 'seats', 'status', 'restaurant_id', 'dining_space_location_id',
        'show_table_name', 'show_table_number', 'show_floorplan', 'pricing', 'show_premium_table_bid', 'premium_table_bid', 'min_bid', 'max_bid'
    ];

    public function reservations()
    {
        return $this->hasMany('App\Models\Reservation');
    }

    public function is_available($start_time, $end_time)
    {
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);

        $reservations = $this->reservations()->where(function ($query) use ($start_time, $end_time) {
            $query->where(function ($q) use ($start_time, $end_time) {
                $q->where('start_time', '>=', $start_time)
                    ->where('start_time', '<', $end_time);
            })
                ->orWhere(function ($q) use ($start_time, $end_time) {
                    $q->where('end_time', '>', $start_time)
                        ->where('end_time', '<=', $end_time);
                })
                ->orWhere(function ($q) use ($start_time, $end_time) {
                    $q->where('start_time', '<', $start_time)
                        ->where('end_time', '>', $start_time);
                })
                ->orWhere(function ($q) use ($start_time, $end_time) {
                    $q->where('start_time', '<', $end_time)
                        ->where('end_time', '>', $end_time);
                })
                ->orWhere(function ($q) use ($start_time, $end_time) {
                    $q->where('start_time', '=', date('Y-m-d H:i:s', $start_time))
                        ->where('end_time', '=', date('Y-m-d H:i:s', $end_time));
                });
        })->get();

       return $reservations->count() === 0;
    }

    public function merge($new_table_number, $old_tables)
    {

        $totalSeats = 0;

        // count merged table seats
        foreach ($old_tables as $old_table) {
            $totalSeats += $old_table->seats;

        }

        $merged_table = new Table();
        $merged_table->name = $old_tables[0]->name;
        $merged_table->dining_space_location_id = $old_tables[0]->dining_space_location_id;
        $merged_table->number = $new_table_number;
        $merged_table->seats = $totalSeats;
        $merged_table->shape = $old_tables[0]->shape;
        $merged_table->restaurant_id = $old_tables[0]->restaurant_id;
        $merged_table->save();

        foreach ($old_tables as $old_table) {
            // soft delete old table record
            $old_table->delete();

        }

    }

    public function split($table_number, $new_tables)
    {
        // Retrieve the table to be split
        $table = Table::where('number', $table_number)->first();

        // Create new table records in the database
        foreach ($new_tables as $newtable) {
            $new_table = new Table();
            $new_table->name = $table->name;
            $new_table->dining_space_location_id = $table->dining_space_location_id;
            $new_table->number = $newtable->number;
            $new_table->seats = $newtable->seats;
            $new_table->shape = $table->shape;
            $new_table->restaurant_id = $newtable->restaurant_id;
            $new_table->save();
        }

        // soft delete existing table record
        $table->delete();
    }

    public function seatingArrangements()
    {
        return $this->hasMany(SeatingArrangement::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningSpaceLocation()
    {
        return $this->belongsTo(DiningSpaceLocation::class);
    }
}
