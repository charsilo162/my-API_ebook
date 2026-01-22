<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Center;
use App\Models\Course;
use App\Http\Resources\StatsResource;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $tutorId = Auth::id();

        if (!$tutorId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Logic for counts
        $totalCourses = Course::count();
        $coursesWithVideo = Course::whereHas('videos')->count(); // Using Eloquent relationship if defined
        
        $hisCourseIds = Course::where('assigned_tutor_id', $tutorId)->pluck('id');

        $data = [
            'owned_centers'                 => DB::table('center_tutor')->where('tutor_id', $tutorId)->count(),
            'total_centers'                 => Center::count(),
            'total_courses'                 => $totalCourses,
            'courses_with_video'            => $coursesWithVideo,
            'courses_without_video'         => $totalCourses - $coursesWithVideo,
            'enrolled_users_in_his_courses' => DB::table('course_user')->whereIn('course_id', $hisCourseIds)->distinct('user_id')->count('user_id'),
            'owned_courses'                 => Course::where('uploader_user_id', $tutorId)->count(),
        ];

        return new StatsResource((object) $data);
    }
}