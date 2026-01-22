<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Relations\MorphMany;


class Book extends Model

{

    use HasFactory;

    protected $fillable = [
            'name',
            'address',
            'city',
            'description',
            'years_of_experience',
            'center_thumbnail_url',
];
    /**

     * Get the courses physically offered by the Center.

     */

                // app/Models/Book.php
            public function category()
            {
                return $this->belongsTo(Category::class);
            }

            public function variants()
            {
                return $this->hasMany(BookVariant::class);
            }

            // Helper to check if it has a digital version
            public function hasDigitalVersion()
            {
                return $this->variants()->where('type', 'digital')->exists();
            }

          
    // --- Polymorphic Relations ---


    public function likes(): MorphMany

    {

        return $this->morphMany(Like::class, 'likeable');

    }


    public function comments(): MorphMany

    {

        return $this->morphMany(Comment::class, 'commentable');

    }

   

    public function shares(): MorphMany

    {

        return $this->morphMany(Share::class, 'shareable');

    }


}