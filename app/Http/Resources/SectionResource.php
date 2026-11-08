<?php

// FILE: app/Http/Resources/SectionResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    /** Section with its lessons (only when eager-loaded). */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'order' => $this->order,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
