<?php
namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory,HasUuid;

    // A Vendor is a profile for a User
    protected $fillable = ['user_id', 'store_name', 'bio', 'balance'];

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    // A Vendor has many Physical Branches (Bookshops)
    public function bookshops()
    {
        return $this->hasMany(Bookshop::class, 'vendor_id'); 
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}