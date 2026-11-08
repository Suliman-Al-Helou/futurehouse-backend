<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseAdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            'level' => $this->level,
            'status' => $this->status,

            'total_duration' => $this->total_duration,
            'requirements' => $this->requirements ?? [],
            'target_audience' => $this->target_audience ?? [],
            'what_you_learn' => $this->what_you_learn ?? [],

            'students_count' => $this->approved_count ?? 0,
            'enrollments_count' => $this->enrollments_count ?? 0,
            'rating' => $this->rating,
            'is_popular' => $this->is_popular,
            'price' => $this->price,
            'is_public' => $this->is_public,
            'instructor' => $this->whenLoaded('instructor', fn () => $this->instructor
                ? ['id' => $this->instructor->id, 'name' => $this->instructor->name]
                : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
