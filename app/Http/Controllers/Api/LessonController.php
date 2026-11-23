<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $course = $lesson->getCourse();

        abort_if($course->status === 'draft', 404);

        $user = $request->user();
        $isManager = $user && Gate::forUser($user)->allows('manageAsInstructor', $course);

        // Draft lessons strictly return 404 for students and unauthenticated visitors
        if (! ($lesson->is_published ?? true) && ! $isManager) {
            abort(404, 'الدرس غير متاح');
        }

        Gate::authorize('watchLesson', [$course, $lesson]);

        return response()->json(new LessonResource($lesson));
    }
}
