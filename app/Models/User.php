<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory,HasApiTokens, Notifiable,HasUuid;

    protected $fillable = [
    'name',
    'first_name',
    'last_name',
    'email',
    'password',
    'phone',
    'photo_path',
    'type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    /**
         * Check if the user is an admin.
         *
         * @return bool
         */
    
    /**
     * Get all likes made by the User.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }
    
    /**
     * Get all comments made by the User.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
    
    /**
     * Get all shares made by the User.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }


 

        public function library()
        {
            return $this->hasMany(UserLibrary::class);
        }
        public function libraryBooks()
        {
            // This allows $user->libraryBooks to return the actual Book objects
            return $this->hasManyThrough(Book::class, UserLibrary::class, 'user_id', 'id', 'id', 'book_id');
        }
        public function vendorProfile()
        {
            return $this->hasOne(Vendor::class);
        }

        public function orders()
        {
            return $this->hasMany(Order::class);
        }

        /**
 * Check if the user has purchased a specific book variant.
 * * @param int|string $variantId
 * @return bool
 */
    public function hasPurchased($variantId): bool
            {
                // Option A: Check the UserLibrary table (Recommended)
                // This assumes your UserLibrary table has a 'book_variant_id' column
                return $this->library()->where('book_variant_id', $variantId)->exists();
                
                /* // Option B: If you prefer checking through Orders:
                return $this->orders()
                    ->where('status', 'completed')
                    ->whereHas('items', function($query) use ($variantId) {
                        $query->where('book_variant_id', $variantId);
                    })->exists();
                */
            }

}