<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StatsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'total_books' => $this['total_books'],
            'types' => [
                'hard_copy' => $this['total_physical'],
                'soft_copy' => $this['total_digital'],
            ],
            'total_books_bought' => $this['total_sold'],
            'total_users' => $this['total_users'],
            'total_vendors' => $this['total_vendors'],
        ];
    }
}