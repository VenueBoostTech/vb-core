<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlogCategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            [
                'name' => 'Menu'
            ],
            [
                'name' => 'Kitchen'
            ],
            [
                'name' => 'Events'
            ],
            [
                'name' => 'Promotions'
            ],
        ];

        foreach ($categories as $category) {
            BlogCategory::create($category);
        }
    }
}
