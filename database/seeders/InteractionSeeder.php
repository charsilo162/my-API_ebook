<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Course;
use App\Models\Video;
use App\Models\Center;
use App\Models\Comment;
use App\Models\Share;

class InteractionSeeder extends Seeder
{
    public function run(): void
    {
        $users   = User::pluck('id');
        $centers = Center::all();
        $courses = Course::all();
        $videos  = Video::all();

        // ✅ Hard safety check
        if ($users->count() < 1) {
            echo "InteractionSeeder skipped: No users found.\n";
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | LIKES (MAX 4 PER ITEM)
        |--------------------------------------------------------------------------
        */
        $this->seedLikes($centers, $users, Center::class);
        $this->seedLikes($courses, $users, Course::class);
        $this->seedLikes($videos,  $users, Video::class);

        /*
        |--------------------------------------------------------------------------
        | COMMENTS (EXACTLY 4 PER ITEM)
        |--------------------------------------------------------------------------
        */
        $this->seedComments($centers, Center::class);
        $this->seedComments($courses, Course::class);
        $this->seedComments($videos,  Video::class);

        /*
        |--------------------------------------------------------------------------
        | SHARES (EXACTLY 4 PER ITEM)
        |--------------------------------------------------------------------------
        */
        $this->seedShares($centers, Center::class);
        $this->seedShares($courses, Course::class);
        $this->seedShares($videos,  Video::class);
    }

    /*
    |--------------------------------------------------------------------------
    | LIKES (SAFE, NO DUPLICATES)
    |--------------------------------------------------------------------------
    */
    protected function seedLikes($items, $userIds, $type): void
    {
        $items->each(function ($item) use ($userIds, $type) {

            $likers = $userIds->take(4); // ✅ max 4 users
            $now = now();

            $data = $likers->map(fn ($userId) => [
                'user_id' => $userId,
                'likeable_id' => $item->id,
                'likeable_type' => $type,
                'type' => rand(0, 1) ? 'up' : 'down',
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('likes')->insertOrIgnore($data);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | COMMENTS (4 PER ITEM)
    |--------------------------------------------------------------------------
    */
    protected function seedComments($items, $type): void
    {
        $items->each(function ($item) use ($type) {

            Comment::factory()
                ->count(4)
                ->state([
                    'commentable_id' => $item->id,
                    'commentable_type' => $type,
                ])
                ->create();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SHARES (4 PER ITEM)
    |--------------------------------------------------------------------------
    */
    protected function seedShares($items, $type): void
    {
        $items->each(function ($item) use ($type) {

            Share::factory()
                ->count(4)
                ->state([
                    'shareable_id' => $item->id,
                    'shareable_type' => $type,
                ])
                ->create();
        });
    }
}
