<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    // Define your list of fixed categories
  const FIXED_CATEGORIES = [
   'UI/UX Design',
'Tailoring & Fashion',
'Web Programming',
'Barbing & Haircut',
'Data Analysis',
'Graphics Design',
'Software Development',
'Digital Marketing',
'Photography & Videography',
'Mobile App Development',
'Catering & Culinary Arts',
'Auto Mechanics',
'Electrical Installation',
'Project Management',
'Cybersecurity',
'Content Writing & Copywriting',
'Event Planning & Management',
'Accounting & Finance',
    'Project Management',
    'Culinary Arts',
    'Music & Audio Production',
    'Health & Fitness Coaching',
    'Photography & Videography',
    'Foreign Languages',


    ];

    // Static counter to ensure every category gets a unique number
    protected static int $sequenceNumber = 0;

 public function definition(): array
{
    self::$sequenceNumber++;
    $fixedName = self::FIXED_CATEGORIES[(self::$sequenceNumber - 1) % count(self::FIXED_CATEGORIES)];
    $name = $fixedName . ' (' . self::$sequenceNumber . ')';

    return [
        'name' => $name,
        'slug' => Str::slug($name . '-' . Str::random(5)), // 🔥 always unique
        'thumbnail_url' => $this->faker->imageUrl(300, 200, 'abstract', true),
    ];
}
}