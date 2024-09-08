<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareOccupancyRateData extends Command
{
    protected $signature = 'occupancy:prepare-data {split_ratio=0.8 : The ratio for splitting the data}';

    protected $description = 'Prepare historical occupancy rate data';

    public function handle()
    {
        $splitRatio = $this->argument('split_ratio');

        // Step 1: Gather historical occupancy rate data
        $occupancyData = DB::table('table_reservations')
            ->select(DB::raw('DATE(start_time) as date'), DB::raw('COUNT(*) as reservations_count'))
            ->groupBy(DB::raw('DATE(start_time)'))
            ->orderBy('date')
            ->get();

        // Step 2: Clean the data
        // Remove any missing values or outliers (if applicable)

        // Remove missing values
        $occupancyData = $occupancyData->filter(function ($record) {
            return $record->date !== null && $record->reservations_count !== null;
        });

        // Step 3: Split the data into training and testing sets
        $splitIndex = (int) ($splitRatio * count($occupancyData));

        $trainingData = $occupancyData->slice(0, $splitIndex);
        $testingData = $occupancyData->slice($splitIndex);

        // Store the prepared data in a database table
        DB::table('prepared_occupancy_data')->insert([
            'data_type' => 'training',
            'data' => json_encode($trainingData),
        ]);

        DB::table('prepared_occupancy_data')->insert([
            'data_type' => 'testing',
            'data' => json_encode($testingData),
        ]);

        $this->info('Data prepared successfully and stored in the database.');
    }
}
