<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDetailResource extends JsonResource
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
            'instructor_name' => $this->instructor_name,
            'rating' => $this->rating,
            'is_popular' => $this->is_popular,
            'price' => $this->price,
            'is_public' => $this->is_public,
            'instructor_id' => $this->instructor_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
