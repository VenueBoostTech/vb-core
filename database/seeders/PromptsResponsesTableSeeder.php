<?php

namespace Database\Seeders;

use App\Models\PromptsResponses;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PromptsResponsesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PromptsResponses::create([
            'prompt' => 'What are your vegan options?',
            'response' => 'We have several vegan options on our menu, including our tofu stir fry and our vegetable curry.'
        ]);

        PromptsResponses::create([
            'prompt' => 'What time do you close?',
            'response' => 'We close at 10pm every day.'
        ]);

        PromptsResponses::create([
            'prompt' => 'Do you have gluten-free options?',
            'response' => 'Yes, we have several gluten-free options on our menu, including our gluten-free pizza and our quinoa salad.'
        ]);

        PromptsResponses::create([
            'prompt' => 'What is your most popular dish?',
            'response' => 'Our most popular dish is our signature burger, which is made with a special blend of Angus beef and topped with caramelized onions and cheddar cheese.'
        ]);

        PromptsResponses::create([
            'prompt' => 'Do you offer delivery?',
            'response' => 'Yes, we offer delivery within a 5-mile radius of our restaurant.'
        ]);
    }
}
