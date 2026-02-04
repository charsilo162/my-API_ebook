<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class BookVariantResource extends JsonResource
{
    public function toArray($request)
    {
        $hasDiscount = $this->discount_price && $this->discount_price < $this->price;
        
        return [
            'id' => $this->id,
            'type' => $this->type, // digital or physical
            'label' => $this->type === 'digital' ? 'E-Book (Soft Copy)' : 'Hardcover (Physical)',
            'price' => (float) $this->price,
            'discount_price' => (float) $this->discount_price,
            'is_on_sale' => $hasDiscount,
            'discount_percentage' => $hasDiscount 
                ? round((($this->price - $this->discount_price) / $this->price) * 100) 
                : 0,
            'in_stock' => $this->type === 'digital' ? true : ($this->stock_quantity > 0),
            'stock_count' => $this->type === 'digital' ? 'Unlimited' : $this->stock_quantity,
            // Only show file path if the user has purchased it (logic can be added here)
            // 'download_link' => $this->when(Auth::user()?->hasPurchased($this->id), $this->file_path),
        'download_link' => $this->when(
                        Auth::check() && (
                            Auth::user()->hasPurchased($this->id) || 
                            Auth::id() === $this->book->vendor_id
                        ), 
                        $this->file_path
                    ),
        
            ];
    }
}