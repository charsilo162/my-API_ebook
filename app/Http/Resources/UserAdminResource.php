<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'uuid'       => $this->uuid,
            'full_name'  => $this->first_name . ' ' . $this->last_name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'type'       => $this->type,
            'photo'      => $this->photo_path ?? asset('images/default-avatar.png'),
            'is_active'  => (bool) $this->is_active,
            'joined_at'  => $this->created_at->format('Y-m-d'),
        ];
    }
}