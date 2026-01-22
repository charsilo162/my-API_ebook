<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'likeable',
        'type',
        'likeable_id',
        'likeable_type',
];

    /**
     * Get the user who made the like.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent likeable model (course, video, or center).
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }
}