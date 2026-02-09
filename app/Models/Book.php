<?php
namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Book extends Model

{

    use HasFactory,HasUuid;
   protected function casts(): array
    {
        return [
            'created_at' => 'date',
       
        ];
    }
    protected $fillable = [
            'uuid',   
            'vendor_id',   
            'category_id', 
            'title',       
            'slug',        
            'author_name', 
            'description',
            'cover_image', 
        ];

        

        protected static function booted()
        {
            static::creating(function ($model) {
                if (!$model->uuid) {
                    $model->uuid = Str::uuid();
                }
            });
        }
    // protected static function boot()
    // {
    //     parent::boot();
    //     static::creating(fn($c) => $c->slug = Str::slug($c->title));
    //     static::updating(function ($c) {
    //         if ($c->isDirty('title')) $c->slug = Str::slug($c->title);
    //     });
    // }
    public function getRouteKeyName()
        {
            return 'uuid';
        }        
    public function vendor()
        {
            // Based on your Vendor model, a Vendor hasMany Books
            return $this->belongsTo(Vendor::class, 'vendor_id');
        }
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