<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    /**
     * Get the courses for the category.
     */
   protected $fillable = ['name', 'slug'];

  
    protected static function boot()
    {
        parent::boot();

        static::creating(fn($c) => $c->slug = Str::slug($c->name));
        static::updating(function ($c) {
            if ($c->isDirty('name')) $c->slug = Str::slug($c->name);
        });
    }

      public function books()
    {
        return $this->hasMany(Book::class);
    }
 
}