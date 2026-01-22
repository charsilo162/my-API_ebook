<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    use HasFactory;

    /**
     * Get the Course this vendor belongs to.
     */

        protected $fillable = [
            'user_id',
            'store_name',
            'bio',
            'balance',
            ];
 // app/Models/Vendor.php
        public function books()
        {
            return $this->hasMany(Book::class);
        }

        public function bookshops()
        {
            return $this->hasMany(Bookshop::class);
        }

        // app/Models/Bookshop.php
        public function vendor()
        {
            return $this->belongsTo(Vendor::class);
        }
}