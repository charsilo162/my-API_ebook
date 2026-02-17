<?php
// app/Models/Order.php
namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuid;
    protected $fillable = [
    'user_id',
    'reference', 
    'total_amount', 
    'payment_status', 
    'status', 
    'order_type'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
 



}
