<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */

        public function toArray($request)
        {
            return [
                'library_id' => $this->id,
                'purchased_at' => $this->purchased_at->format('M d, Y'),
                // Reach into the book relationship
                'title' => $this->book->title,
                'cover_image' => $this->book->cover_image,
                'author' => $this->book->author_name,
            ];
        }

}