<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            //'slug' => \Str::slug($this->title),
            'author' => $this->author_name,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            'category' => new CategoryResource($this->whenLoaded('category')),
            
            // Helpful for UI: show the lowest available price
            'starting_price' => $this->variants->min('price'),
            
            // Group variants by type for the frontend
            'formats' => BookVariantResource::collection($this->whenLoaded('variants')),
            
            'created_at' => $this->created_at->format('Y-m-d'),
            'vendor' => [
                'id' => $this->vendor->id,
                'store_name' => $this->vendor->store_name,
            ],
        ];
    }
}