<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
  public function toArray($request)
{
    return [
        'id'         => $this->id,
        'name'       => $this->name,
        'email'      => $this->email,
        'type'       => $this->type,
        // 'photo_path' => $this->photo_path ? asset('storage/' . $this->photo_path) : null,
        // // Add the full URL here
        // 'photo_url'  => $this->photo_path ? asset('storage/' . $this->photo_path) : null,


        'photo_path' => $this->photo_path && str_starts_with($this->photo_path, 'http') ? $this->photo_path
            : ($this->photo_path ? asset('storage/' . $this->photo_path) : asset('storage/default-avatar.png')),
            
        'photo_url' => $this->photo_path && str_starts_with($this->photo_path, 'http') ? $this->photo_path
            : ($this->photo_path ? asset('storage/' . $this->photo_path) : asset('storage/default-avatar.png')),
            
            'created_at' => $this->created_at,
    ];
}
}
