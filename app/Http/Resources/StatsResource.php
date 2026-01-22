<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
class StatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'centers' => [
                'owned' => $this->owned_centers,
                'total' => $this->total_centers,
            ],
            'courses' => [
                'total'         => $this->total_courses,
                'owned'         => $this->owned_courses,
                'with_video'    => $this->courses_with_video,
                'without_video' => $this->courses_without_video,
            ],
            'engagement' => [
                'total_enrolled_users' => $this->enrolled_users_in_his_courses,
            ],
        ];
    }
}