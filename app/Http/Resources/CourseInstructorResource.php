<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseInstructorResource extends JsonResource
{
    /**
     * Transform the resource into an array with educational metadata only.
     * Sensitive administrative attributes (price, youtube_playlist_id, is_public, status) are stripped.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cover_image' => $this->cover_image,
            'level' => $this->level,
            'total_duration' => $this->total_duration,
            'requirements' => $this->requirements ?? [],
            'target_audience' => $this->target_audience ?? [],
            'what_you_learn' => $this->what_you_learn ?? [],
            'rating' => $this->rating,
            'students_count' => $this->approved_count ?? $this->students_count ?? 0,
            'instructor' => $this->whenLoaded('instructor', fn () => $this->instructor
                ? ['id' => $this->instructor->id, 'name' => $this->instructor->name]
                : null),
        ];
    }
}
