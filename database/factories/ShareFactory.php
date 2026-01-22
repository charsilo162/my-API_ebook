<?php

namespace Database\Factories;

use App\Models\Share;
use App\Models\User;
use App\Models\Course;
use App\Models\Video;
use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShareFactory extends Factory
{
    protected $model = Share::class;

    public function definition(): array
    {
        // Define the possible models and how to select a random ID from them
        $shareables = [
            Course::class => Course::pluck('id')->toArray(),
            Video::class => Video::pluck('id')->toArray(),
            Center::class => Center::pluck('id')->toArray(),
        ];

        // Choose a random shareable model class
        $shareableType = $this->faker->randomElement(array_keys($shareables));
        
        // Select a random existing ID for the chosen model type
        $shareableId = $this->faker->randomElement($shareables[$shareableType]);

        return [
            'user_id' => User::all()->random()->id,
            'platform' => $this->faker->randomElement(['Facebook', 'Twitter', 'Email', 'WhatsApp']),
            
            'shareable_id' => $shareableId,
            'shareable_type' => $shareableType,
        ];
    }
}