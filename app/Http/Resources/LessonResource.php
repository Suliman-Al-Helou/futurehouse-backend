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
    protected bool $isSingleLesson = false;

    /**
     * Mark this resource instance explicitly as a single lesson request context.
     */
    public function singleLesson(bool $value = true): self
    {
        $this->isSingleLesson = $value;

        return $this;
    }

    /** Lesson shape consumed by CourseSectionsModal, Curriculum, and LessonController. */
    public function toArray(Request $request): array
    {
        /** @var VideoDeliveryManager $videoManager */
        $videoManager = app(VideoDeliveryManager::class);

        // Enforce gate strictly for public course curriculum listings (e.g. GET /api/courses/{course})
        // Individual lesson endpoints (show, store, update) do NOT enforce gate
        $isPublicCurriculum = (bool) ($this->additional['enforce_gate'] ?? false)
            || ($request->is('api/courses/*') && ! $request->is('api/courses/*/progress*') && ! $request->is('api/courses/*/enroll*'));
        $isSingleLessonRequest = $this->isSingleLesson
            || (bool) ($this->additional['is_single_lesson'] ?? false)
            || $request->is('api/lessons/*');

        $enforceGate = $isPublicCurriculum && ! $isSingleLessonRequest;

        $playback = $videoManager->resolvePlayback(
            $this->resource,
            enforceGate: $enforceGate
        );

        return [
            'id' => $this->id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_id' => $enforceGate && ! $this->is_preview ? null : $this->video_id,
            'video_provider' => $this->video_provider ?? config('services.video.default', 'youtube'),
            'playback' => $playback,
            'duration' => $this->duration,
            'order' => $this->order,
            'is_preview' => (bool) $this->is_preview,
            'is_published' => (bool) ($this->is_published ?? true),
        ];
    }
}
