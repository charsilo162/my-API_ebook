<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
        {
            return [
                'id'         => $this->id,
                'reference'  => $this->reference ?? 'N/A', // Handle null reference
                'status'     => $this->status ?? 'pending', // Handle null status
                'order_type' => $this->order_type,
                'customer'   => [
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ],
                'items' => $this->items->map(function($item) {
                    return [
                        'book_title'  => $item->book->title,
                        'cover_image' => $item->book->cover_image, // ADD THIS LINE
                        'type'        => $item->variant->type,
                        'price'       => $item->price,
                    ];
                }),
                // Change 'total' to 'total_amount' to match your Blade @props
                'total_amount' => $this->total_amount, 
                'created_at'   => $this->created_at->format('d M Y'),
            ];
        }
}