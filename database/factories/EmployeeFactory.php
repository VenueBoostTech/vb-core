<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Employee;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail,
            'role_id' => fake()->randomElement(Role::pluck('id')->toArray()),
            'manager_id' => fake()->optional(0.5)->randomElement(Employee::where('role_id', Role::where('name', 'manager')->first()->id)->pluck('id')->toArray()),
            'owner_id' => fake()->optional(0.5)->randomElement(Employee::where('role_id', Role::where('name', 'owner')->first()->id)->pluck('id')->toArray()),
            'salary' => fake()->randomFloat(2, 1000, 10000),
            'salary_frequency' => fake()->randomElement(['daily', 'weekly', 'bi-weekly', 'monthly']),
        ];
    }
}
