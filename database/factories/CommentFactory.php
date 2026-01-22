<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\User;
use App\Models\Course;
use App\Models\Video;
use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        // Define the possible models and how to select a random ID from them
        $commentables = [
            Course::class => Course::pluck('id')->toArray(),
            Video::class => Video::pluck('id')->toArray(),
            Center::class => Center::pluck('id')->toArray(),
        ];

        // Choose a random commentable model class
        $commentableType = $this->faker->randomElement(array_keys($commentables));
        
        // Select a random existing ID for the chosen model type
        $commentableId = $this->faker->randomElement($commentables[$commentableType]);

        return [
            'user_id' => User::all()->random()->id,
            'body' => $this->faker->paragraph(rand(1, 3)),
            
            'commentable_id' => $commentableId,
            'commentable_type' => $commentableType,
        ];
    }
}