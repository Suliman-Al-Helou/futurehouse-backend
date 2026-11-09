<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InstructorResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'specializations' => $this->specializations ?? [],
            'years_experience' => $this->years_experience,
            'rating' => $this->rating,
            'total_reviews' => $this->total_reviews,
            'students_count' => $this->students_count,
            'achievements' => $this->achievements ?? [],
            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,
            'youtube' => $this->youtube,
            'courses_count' => $this->courses_count ?? 0,
        ];
    }
}
