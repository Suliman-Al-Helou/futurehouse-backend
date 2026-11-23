<?php

// FILE: app/Http/Resources/LessonResource.php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lesson;
use App\Services\Video\VideoDeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    /** Lesson shape consumed by CourseSectionsModal, Curriculum, and LessonController. */
    public function toArray(Request $request): array
    {
        /** @var VideoDeliveryManager $videoManager */
        $videoManager = app(VideoDeliveryManager::class);

        // Determine if this is a single lesson request or general curriculum listing
        $routeLesson = $request->route('lesson');
        $singleLessonId = $routeLesson instanceof Lesson ? $routeLesson->id : $routeLesson;

        $isSingleLessonRequest = (bool) ($this->additional['is_single_lesson'] ?? false)
            || ($singleLessonId !== null && ((int) $singleLessonId === (int) $this->id));
        $enforceGate = ! $isSingleLessonRequest;

        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_id' => $this->video_id,
            'video_provider' => $this->video_provider ?? config('services.video.default', 'youtube'),
            'playback' => $videoManager->resolvePlayback(
                $this->resource,
                $request->user(),
                enforceGate: $enforceGate
            ),
            'duration' => $this->duration,
            'order' => $this->order,
            'is_preview' => (bool) $this->is_preview,
            'is_published' => (bool) ($this->is_published ?? true),
        ];
    }
}
