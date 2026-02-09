<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserLibrary extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'book_id',
        'book_variant_id',
        'purchased_at',
    ];
    protected $casts = [
    'purchased_at' => 'datetime',
];
        // app/Models/UserLibrary.php
        public function user()
        {
            return $this->belongsTo(User::class);
        }

        public function book()
        {
            return $this->belongsTo(Book::class);
        }

        public function variant()
        {
            return $this->belongsTo(BookVariant::class, 'book_variant_id');
        }
    /**
     * Get the Centers the Tutor is affiliated with.
     */
  
}