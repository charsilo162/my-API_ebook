<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
        {
            return [
                'id' => $this->id,
                'reference' => $this->reference,
                'status' => $this->status,
                'order_type' => $this->order_type,
                'customer' => [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
                'items' => $this->items->map(function($item) {
                    return [
                        'book_title' => $item->book->title,
                        'type'       => $item->variant->type, // Digital or Physical from variants table
                        'price'      => $item->price,         // Price at time of purchase
                        'current_variant_price' => $item->variant->price, // Current price in shop
                        'stock_remaining' => $item->variant->stock_quantity,
                        'file_ready' => !empty($item->variant->file_path), 
                    ];
                }),
                'total' => $this->total_amount,
                'created_at' => $this->created_at->format('d M Y'),
            ];
        }
}