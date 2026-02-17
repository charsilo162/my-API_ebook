<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserOrderResource extends JsonResource
{
    public function toArray($request)
    {
        // Define progress bar logic
        $steps = ['pending' => 25, 'processing' => 50, 'shipped' => 75, 'delivered' => 100];

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'progress' => $steps[$this->status] ?? 0,
            'total_amount' => (float) $this->total_amount,
            'created_at' => $this->created_at->format('M d, Y'),
            'items' => $this->items->map(function($item) {
                // Accessing nested data: Item -> Book -> Vendor -> User
                // and Item -> BookVariant -> Bookshop
                return [
                    'title' => $item->book->title,
                    'cover_image' => $item->book->cover_image,
                    'price' => $item->price,
                    'store_name' => $item->book->vendor->store_name ?? 'N/A',


                    'shop_phone' => $item->variant->bookshop->phone ?? 'Phone Not Found',
                    'shop_address' => $item->variant->bookshop->address ?? 'Address Not Found',
                    'shop_city' => $item->variant->bookshop->city ?? '',
                ];
            }),
        ];
    }
}