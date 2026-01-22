<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Share extends Model
{
    use HasFactory;
    protected $fillable = [
'user_id',
'platform',
'shareable_type',
'shareable_id',
'shareable',
];
    /**
     * Get the user who made the share.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent shareable model (course, video, or center).
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }
}