<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

use App\Models\User;
use App\Models\Category;
use App\Models\Center;
use App\Models\Tutor;
use App\Models\Course;
use App\Models\Video;
use App\Models\Price;

class DatabaseSeeder extends Seeder
{
    protected $faker;

    public function __construct()
    {
        $this->faker = Faker::create();
    }

    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. CORE TABLE DATA (ONLY 4 EACH)
        |--------------------------------------------------------------------------
        */

        // User::factory(4)->create();
        $this->call(CategorySeeder::class);

    }
}
