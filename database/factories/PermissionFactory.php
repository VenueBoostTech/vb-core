<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Can appoint new staff',
                'Can view sales report',
                'Can fire existing staff',
                'Can report to manager',
                'Can report to owner',
                'Can manage inventory'
            ]),
        ];
    }
}
