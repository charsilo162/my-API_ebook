<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VideoResource;
use App\Models\Course;
use App\Models\Video;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    protected $cloudinaryService;
    public function __construct(CloudinaryService $cloudinaryService)
        {
            $this->cloudinaryService = $cloudinaryService;
        }
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $tutorId = $user->tutor?->id ?? $user->tutor_id ?? $userId;

        $query = Video::with('courses') // Eager load the courses relationship
                    ->where('uploader_user_id', $userId)
                    ->orWhere('uploader_user_id', $tutorId);

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%$search%");
        }

        $videos = $query->latest()->paginate(12);

        return VideoResource::collection($videos);
    }

    public function show($id)
    {
        // Load video or fail
        $video = Video::findOrFail($id);

        // Authorization check
        if ($video->uploader_user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return new VideoResource($video);
    }
    public function store(Request $request)
    {
        $request->validate([
            'course_id'      => 'required|exists:courses,id',
            'title'          => 'required|string|max:255',
            'video_file'     => 'required|file|mimes:mp4,mov,avi,wmv|max:102400', // 100MB
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'duration'       => 'nullable|integer',
            'order_index'    => 'nullable|integer|min:0',
        ]);

        // 1. Upload Video (Notice the 'video' resource type)
        $videoUrl = $this->cloudinaryService->uploadFile(
            $request->file('video_file'), 
            'course_videos', 
            'video'
        );

        // 2. Upload Thumbnail
        $thumbUrl = null;
        if ($request->hasFile('thumbnail_file')) {
            $thumbUrl = $this->cloudinaryService->uploadFile(
                $request->file('thumbnail_file'), 
                'video_thumbnails'
            );
        }

        $video = Video::create([
            'uploader_user_id' => auth()->id(),
            'title'            => $request->title,
            'video_url'        => $videoUrl,
            'thumbnail_url'    => $thumbUrl,
            'duration'         => $request->duration,
        ]);

        $course = Course::find($request->course_id);
        $course->videos()->syncWithoutDetaching([
            $video->id => ['order_index' => $request->input('order_index', 0)]
        ]);

        return new VideoResource($video);
    }

    public function update(Request $request, Video $video)
    {
        // 1. Authorization check
        // if ($video->uploader_user_id !== auth()->id()) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        // 2. Validation
        $validated = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'duration'       => 'nullable|integer|min:1',
            'publish'        => 'sometimes|boolean',
            'video_file'     => 'nullable|file|mimes:mp4,mov,avi,wmv|max:102400',
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = $validated;

        // 3. Handle Video File Replacement
        if ($request->hasFile('video_file')) {
            // Delete old video from Cloudinary (Note: 'video' resource type)
            if ($video->video_url) {
                $this->cloudinaryService->deleteFile($video->video_url, 'video');
            }

            // Upload new video
            $data['video_url'] = $this->cloudinaryService->uploadFile(
                $request->file('video_file'), 
                'course_videos', 
                'video'
            );
        }

        // 4. Handle Thumbnail Replacement
        if ($request->hasFile('thumbnail_file')) {
            // Delete old thumbnail
            if ($video->thumbnail_url) {
                $this->cloudinaryService->deleteFile($video->thumbnail_url, 'image');
            }

            // Upload new thumbnail
            $data['thumbnail_url'] = $this->cloudinaryService->uploadFile(
                $request->file('thumbnail_file'), 
                'video_thumbnails'
            );
        }

        // 5. Update Database
        $video->update($data);

        return new VideoResource($video);
    }

public function destroy(Video $video)
{
    if ($video->uploader_user_id !== auth()->id()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // 1. Delete video file (Resource type must be 'video')
    if ($video->video_url) {
        $this->cloudinaryService->deleteFile($video->video_url, 'video');
    }

    // 2. Delete thumbnail file (Default is 'image')
    if ($video->thumbnail_url) {
        $this->cloudinaryService->deleteFile($video->thumbnail_url);
    }

    $video->delete();

    return response()->json(['message' => 'Video and cloud files deleted successfully']);
}


// public function togglePublish(Video $video)
// {
//     $this->authorizeVideo($video);
//     $video->update(['publish' => !$video->publish]);
//     return new VideoResource($video);
// }
public function togglePublish(Video $video)
{
    // Ensure the logged-in user is the owner/uploader
    // if ($video->uploader_user_id !== auth()->id()) {
    //     return response()->json(['error' => 'Unauthorized'], 403);
    // }

    // Toggle the publish status
    $video->publish = !$video->publish;
    $video->save();

    return new VideoResource($video);
}

private function authorizeVideo(Video $video)
{
    $userId = auth()->id();
    $tutorId = auth()->user()?->tutor?->id ?? $userId;

    if (!in_array($video->uploader_user_id, [$userId, $tutorId])) {
        abort(403);
    }
}
}