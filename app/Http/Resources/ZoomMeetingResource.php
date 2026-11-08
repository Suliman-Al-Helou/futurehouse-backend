<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoomMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'zoom_link' => $this->zoom_link,
            'starts_at' => $this->starts_at,
            'duration_minutes' => $this->duration_minutes,
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'title' => $this->course->title,
            ]),
            'attendances_count' => $this->whenNotNull($this->attendances_count ?? null),
            'created_at' => $this->created_at,
        ];
    }
}
