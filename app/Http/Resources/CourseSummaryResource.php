<?php

// FILE: app/Http/Resources/CourseSummaryResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseSummaryResource extends JsonResource
{
    /** Compact course card used inside the instructor profile. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'cover_image' => $this->cover_image,
            'level' => $this->level,
            'total_duration' => $this->total_duration,
            'rating' => $this->rating ?? 0,
            'students_count' => $this->approved_count ?? 0,
        ];
    }
}
