<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class VideoUploadService
{
    public function upload(UploadedFile $videoFile, ?UploadedFile $thumbFile, array $data): Video
    {
        $videoPath = $videoFile->store('videos', 'public');
        $videoUrl = Storage::url($videoPath);

        $thumbUrl = null;
        if ($thumbFile) {
            $thumbPath = $thumbFile->store('thumbnails', 'public');
            $thumbUrl = Storage::url($thumbPath);
        }

        $uploaderId = Auth::user()?->tutor?->id ?? Auth::user()?->tutor_id ?? Auth::id();

        return Video::create([
            'uploader_user_id' => $uploaderId,
            'title' => $data['title'],
            'video_url' => $videoUrl,
            'thumbnail_url' => $thumbUrl,
            'duration' => $data['duration'] ?? null,
        ]);
    }

    public function rollback(?string $videoPath, ?string $thumbPath): void
    {
        if ($videoPath) Storage::disk('public')->delete($videoPath);
        if ($thumbPath) Storage::disk('public')->delete($thumbPath);
    }
}