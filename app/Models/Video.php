<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Video extends Model
{
    use HasFactory;
protected $fillable = [
'tutor_id',
'title',
'publish',
'thumbnail_url',
'uploader_user_id',
'video_url',
'duration',
];
    /**
     * Get the Tutor who uploaded the video.
     */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    /**
     * Get the Courses this video is part of.
     */
  
public function courses()
{
    return $this->belongsToMany(Course::class, 'course_video');
}
    /**
     * Get all likes for the video.
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    

    /**
     * Get all comments for the video.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    
    /**
     * Get all shares for the video.
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }
}