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
        // Category::factory(4)->create();

        // $centers = Center::factory(4)->create();
        // $tutors  = Tutor::factory(4)->create();
        // $courses = Course::factory(4)->create();
        // $videos  = Video::factory(4)->create();

        // /*
        // |--------------------------------------------------------------------------
        // | 2. COURSE PRICES (1 PRICE PER COURSE)
        // |--------------------------------------------------------------------------
        // */

        // $courses->each(function (Course $course) {
        //     Price::factory()->for($course)->create();
        // });

        // /*
        // |--------------------------------------------------------------------------
        // | 3. RELATIONSHIPS
        // |--------------------------------------------------------------------------
        // */

        // // A. Course <-> Video (4 videos per course with order_index)
        // $courses->each(function (Course $course) use ($videos) {

        //     $pivotData = [];
        //     $order = 1;

        //     foreach ($videos as $video) {
        //         $pivotData[$video->id] = [
        //             'order_index' => $order++,
        //         ];
        //     }

        //     $course->videos()->attach($pivotData);
        // });

        // // B. Center <-> Course (4 courses per center)
        // $centers->each(function (Center $center) use ($courses) {
        //     $courses->each(function (Course $course) use ($center) {
        //         $center->courses()->attach($course->id, [
        //             'price' => $this->faker->randomFloat(2, 50, 500),
        //             'start_date' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
        //         ]);
        //     });
        // });

        // // C. Center <-> Tutor (4 tutors per center)
        // $centers->each(function (Center $center) use ($tutors) {
        //     $center->tutors()->attach($tutors->pluck('id'));
        // });

        /*
        |--------------------------------------------------------------------------
        | 4. POLYMORPHIC / INTERACTIONS
        |--------------------------------------------------------------------------
        */

        $this->call([
            InteractionSeeder::class,
        ]);
    }
}
