<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'what_you_learn' => $this->what_you_learn ?? [],
            'requirements' => $this->requirements ?? [],
            'target_audience' => $this->target_audience ?? [],
            'students_count' => $this->approved_count ?? $this->students_count ?? 0,
            'rating' => $this->rating ?? 0,
            'reviews' => 0,
            'instructor' => $this->instructor_name ?? '',
            'instructorBio' => '',
            'lessons' => $this->lessons_count ?? 0,
            'tags' => [],
            'price' => $this->price ?? 0,
            'hot' => (bool) ($this->is_popular ?? false),
            'is_public' => (bool) $this->is_public,
        ];
    }
}
