<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'zoom_meeting_id' => $this->zoom_meeting_id,
            'title' => $this->title,
            'description' => $this->description,
            'provider' => $this->provider,
            'provider_video_id' => $this->provider_video_id,
            'duration_seconds' => $this->duration_seconds,
            'type' => $this->type,
            'created_by' => $this->created_by,
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course->id,
                'title' => $this->course->title,
            ]),
            'zoom_meeting' => $this->whenLoaded('zoomMeeting', fn () => [
                'id' => $this->zoomMeeting->id,
                'title' => $this->zoomMeeting->title,
                'starts_at' => $this->zoomMeeting->starts_at,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

