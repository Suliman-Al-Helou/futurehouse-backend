<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Instructor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Instructor
 */
class CourseInstructorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'specializations' => $this->specializations,
            'years_experience' => $this->years_experience,
            'rating' => $this->rating,
            'total_reviews' => $this->total_reviews,
            'students_count' => $this->students_count,
            'achievements' => $this->achievements ?? [],
            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,
            'youtube' => $this->youtube,
            'user' => [
                'name' => $this->user?->name ?? $this->name,
                'email' => $this->user?->email,
            ],
        ];
    }
}
