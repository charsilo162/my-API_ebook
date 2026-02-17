<?php
// app/Models/Bookshop.php
namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BookShop extends Model
{
    use HasUuid;
    protected $table = 'bookshops'; 
      public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['vendor_id', 'shop_name', 'address', 'state', 'city', 'phone'];
        // A Bookshop belongs to a Vendor
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
    // Physical book variants available at this specific shop
    public function bookVariants()
    {
        return $this->hasMany(BookVariant::class);
    }
}