<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray($request)
    {
        // Logic to determine if digital exists
        $hasDigital = $this->variants->contains('type', 'digital');
        $defaultType = $hasDigital ? 'digital' : 'physical';

        return [
            // 'id' => $this->id,
            'id' => $this->uuid,
            'title' => $this->title,
            'library_id' => $this->library_id,
            'author' => $this->author_name,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            
            // NEW: Tell the frontend what type to use for the link
            'default_type' => $defaultType,
             'is_active' => (bool) $this->is_active,
            
            'category' => new CategoryResource($this->whenLoaded('category')),
            
            // This pulls from the withMin() we added in the controller
            'starting_price' => (float) ($this->variants_min_price ?? 0),
            
            'formats' => BookVariantResource::collection($this->whenLoaded('variants')),
            
            'vendor' => [
                'id' => $this->vendor?->id,
                'store_name' => $this->vendor?->store_name ?? 'Unknown Store',
            ],
                'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}