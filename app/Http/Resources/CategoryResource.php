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
           'thumbnail_url' => $this->thumbnail_url ?? asset('storage/images/d5.jpg'),
            'books_count' => $this->whenCounted('books'),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}