<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class BookVariant extends Model
{
    use HasFactory;
    
    /**
     * Get the category that the course belongs to.
     */
    protected $fillable = [
        'book_id',
        'type',
        'price',
        'discount_price',
        'stock_quantity',
        'file_path',
        'bookshop_id',
        ];

    // app/Models/BookVariant.php
            public function book()
            {
                return $this->belongsTo(Book::class);
            }

            // Scopes for easy filtering in your controllers
            public function scopeDigital($query)
            {
                return $query->where('type', 'digital');
            }

            public function scopePhysical($query)
            {
                return $query->where('type', 'physical');
            }

    
}