<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Center;
use App\Models\Course;
use App\Http\Resources\StatsResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function index(Request $request)
        {
            // Use 'tutor' or 'user' based on your Auth guard setup
            $user = Auth::user(); 

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Load counts for this specific user in ONE query
            $userData = User::where('id', $user->id)
                ->withCount([
                    'courses as owned_courses_count',
                    'videos as total_videos_count',
                    // Count courses owned by user that have at least one video
                    'courses as courses_with_video_count' => function ($query) {
                        $query->whereHas('videos');
                    }
                ])
                ->first();

            // For the pivot table center_tutor, we can still use count() 
            // or define a relationship if you have a Tutor model.
            $ownedCentersCount = DB::table('center_tutor')
                ->where('tutor_id', $user->id)
                ->count();

            $data = [
                'owned_centers'         => $ownedCentersCount,
                'total_centers'         => Center::count(), // Global
                'total_courses'         => Course::count(), // Global
                'owned_courses'         => $userData->owned_courses_count,
                'courses_with_video'    => $userData->courses_with_video_count,
                'courses_without_video' => $userData->owned_courses_count - $userData->courses_with_video_count,
                'total_videos_uploaded' => $userData->total_videos_count,
                
                // Enrollments: This is still best as a specific query 
                // unless you define a deep relationship (HasManyThrough)
                'enrolled_users_in_his_courses' => DB::table('course_user')
                    ->whereIn('course_id', $user->courses()->pluck('id'))
                    ->distinct('user_id')
                    ->count('user_id'),
            ];

            return new StatsResource((object) $data);
        }
}