<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BlogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $blogs = [
            [
                'restaurant_id' => null,
                'title' => 'Introducing our new menu',
                'content' => 'We are excited to announce our new menu featuring seasonal ingredients and unique flavor combinations. Come try it out for yourself and let us know what you think!',
                'image' => 'menu.jpg',
                'created_at' => '2022-05-01 00:00:00'
            ],
            [
                'restaurant_id' => null,
                'title' => 'Behind the scenes at our kitchen',
                'content' => 'Ever wonder what goes on in the kitchen of a busy restaurant? Take a look behind the scenes with our head chef and see the hard work and dedication that goes into every dish.',
                'image' => 'kitchen.jpg',
                'created_at' => '2022-05-15 00:00:00'
            ],
        ];

        foreach ($blogs as $blog) {
            Blog::create($blog);
        }
    }
}
