<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray(Request $request): array
    {
        return [
            'course' => [
                'id' => $this->id,
                'title' => $this->title,
                'description' => $this->description,
                'image_thumbnail_url' => $this->image_thumbnail_url,
                'category' => [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name ?? null,
                ],
            ],

            'students' => $this->students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,

                    'payment' => [
                        'reference' => $student->pivot->payment_reference,
                        'amount' => $student->pivot->paid_amount,
                        'paid_at' => $student->pivot->paid_at,
                    ],
                ];
            }),
        ];
    }
}
