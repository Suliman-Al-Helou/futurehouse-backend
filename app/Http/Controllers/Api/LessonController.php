<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
public function show(Lesson $lesson): JsonResponse
{
    $course = $lesson->getCourse();

    abort_if($course->status === 'draft', 404);

    Gate::authorize('watchLesson', [$course, $lesson]);

    return response()->json([
        'id' => $lesson->id,
        'title' => $lesson->title,
        'description' => $lesson->description,
        'video_id' => $lesson->video_id,
        'duration' => $lesson->duration,
        'order' => $lesson->order,
        'is_preview' => $lesson->isPreview(),
    ]);
}
}