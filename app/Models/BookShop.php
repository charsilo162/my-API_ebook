<?php
// app/Models/Bookshop.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookShop extends Model
{
    protected $fillable = ['vendor_id', 'shop_name', 'address', 'city'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Physical book variants available at this specific shop
    public function bookVariants()
    {
        return $this->hasMany(BookVariant::class);
    }
}