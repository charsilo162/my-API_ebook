<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'thumbnail' => $this->thumbnail_url ?? 'https://via.placeholder.com/150',
            'books_count' => $this->whenCounted('books'),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}