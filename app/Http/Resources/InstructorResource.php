<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Instructor
 *
 * @property-read Instructor $resource
 */
class InstructorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return [
            'id' => $this->id,
            'user_id' => $this->when($isAdmin, $this->user_id),
            'is_display_only' => $this->when($isAdmin, is_null($this->user_id)),
            'user' => $this->when($isAdmin, function () {
                if (! $this->resource->relationLoaded('user') || ! $this->user instanceof User) {
                    return null;
                }

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
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
