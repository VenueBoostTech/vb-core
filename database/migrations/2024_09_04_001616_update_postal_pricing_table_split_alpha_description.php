<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update the data
        $pricings = DB::table('postal_pricing')->get();

        foreach ($pricings as $pricing) {
            $parts = explode('~', $pricing->alpha_description);
            $alpha_description = trim($parts[0]);
            $notes = isset($parts[1]) ? trim($parts[1]) : null;

            DB::table('postal_pricing')
                ->where('id', $pricing->id)
                ->update([
                    'alpha_description' => $alpha_description,
                    'notes' => $notes,
                ]);
        }
    }

    public function down()
    {
        // If you need to rollback, combine alpha_description and notes
        $pricings = DB::table('postal_pricing')->get();

        foreach ($pricings as $pricing) {
            $alpha_description = $pricing->alpha_description . ($pricing->notes ? '~' . $pricing->notes : '');

            DB::table('postal_pricing')
                ->where('id', $pricing->id)
                ->update([
                    'alpha_description' => $alpha_description,
                    'notes' => null,
                ]);
        }
    }
};
