<?php

// FILE: app/Http/Resources/LessonResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /** Lesson shape consumed by CourseSectionsModal. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_id' => $this->video_id,
            'duration' => $this->duration,
            'order' => $this->order,
            'is_preview' => $this->is_preview,
        ];
    }
}
