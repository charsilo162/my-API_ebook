<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request)
    {
    return [
        'id' => $this->id,
        'body' => $this->body,
        'user' => [
            'name' => $this->user->name,
            'avatar' =>  asset('storage/avater.jpg'),
           
        ],
        'created_at' => $this->created_at->diffForHumans(),
    ];
    }
}