<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Owner
        $owner = \App\Models\Employee::factory()->create([
            'name' => 'Jenny Wilson',
            'email' => 'j.wilson@restaurant.sn-boost.com',
            'role_id' => 2,
            'salary' => 100000,
            'salary_frequency' => 'monthly',
            'restaurant_id' => 1
        ]);

        $restaurant = \App\Models\Restaurant::find(1);
        $restaurant->user_id = $owner->id;
        $restaurant->save();

        // Manager
        $manager1 = \App\Models\Employee::factory()->create([
            'name' => 'Esther Howard',
            'email' => 'e.howard@restaurant.sn-boost.com',
            'role_id' => 1,
            'owner_id' => $owner->id,
            'salary' => 10000,
            'salary_frequency' => 'monthly',
            'user_id' => 1,
            'hire_date' => '2023-01-01'
        ]);

        $manager2 = \App\Models\Employee::factory()->create([
            'name' => 'Kathryn Murphy',
            'email' => 'k.murphy@restaurant.sn-boost.com',
            'role_id' => 1,
            'owner_id' => $owner->id,
            'salary' => 5000,
            'salary_frequency' => 'monthly',
            'hire_date' => '2023-02-01'
        ]);

        // Waiter
        $waiter1 = \App\Models\Employee::factory()->create([
            'name' => 'Brooklyn Simmons',
            'email' => 'b.simmons@restaurant.sn-boost.com',
            'role_id' => 3,
            'manager_id' => $manager1->id,
            'salary' => 1500,
            'salary_frequency' => 'bi-weekly',
            'hire_date' => '2023-03-01'
        ]);

        $waiter2 = \App\Models\Employee::factory()->create([
            'name' => 'John Doe',
            'email' => 'j.doe@restaurant.sn-boost.com',
            'role_id' => 3,
            'manager_id' => $manager2->id,
            'salary' => 2500,
            'salary_frequency' => 'monthly',
            'hire_date' => '2023-04-01'
        ]);

        // Cook
        $cook = \App\Models\Employee::factory()->create([
            'name' => 'Jimmy Murphy',
            'email' => 'j.murphy@restaurant.sn-boost.com',
            'role_id' => 4,
            'manager_id' => $manager2->id,
            'salary' => 7000,
            'salary_frequency' => 'monthly',
            'hire_date' => '2023-04-11'
        ]);
    }
}
