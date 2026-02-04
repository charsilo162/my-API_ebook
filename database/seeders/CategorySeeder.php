<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Fiction',
            'Non-Fiction',
            'Science Fiction',
            'Fantasy',
            'Romance',
            'Mystery',
            'Thriller',
            'Horror',
            'Biography',
            'Autobiography',
            'Self-Help',
            'Business & Economics',
            'Technology',
            'Programming',
            'Computer Science',
            'Cyber Security',
            'Artificial Intelligence',
            'Data Science',
            'Machine Learning',
            'Web Development',
            'Mobile App Development',
            'History',
            'Politics',
            'Philosophy',
            'Religion & Spirituality',
            'Health & Fitness',
            'Psychology',
            'Education',
            'Children’s Books',
            'Young Adult',
            'Poetry',
            'Comics & Graphic Novels',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
