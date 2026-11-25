<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Lesson;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InstructorTaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService
    ) {}

    /**
     * Returns the lesson's task or 404.
     */
    public function show(Lesson $lesson): JsonResponse
    {
        $this->authorizeLessonCourse($lesson);

        $task = $lesson->task()->first();
        if ($task === null) {
            abort(404, 'لا توجد مهمة لهذا الدرس');
        }

        return response()->json(new TaskResource($task));
    }

    /**
     * Creates a task for the lesson (one per lesson).
     */
    public function store(TaskRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeLessonCourse($lesson);

        $task = $this->taskService->store($lesson, $request->validated());

        return response()->json(new TaskResource($task), 201);
    }

    /**
     * Updates the lesson's task.
     */
    public function update(UpdateTaskRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeLessonCourse($lesson);

        $task = $lesson->task()->firstOrFail();
        $task->update($request->validated());

        return response()->json(new TaskResource($task));
    }

    /**
     * Deletes the lesson's task after verifying no student attempts exist (Task Integrity Guard).
     */
    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorizeLessonCourse($lesson);

        $task = $lesson->task()->first();
        if ($task === null) {
            abort(404, 'المهمة غير موجودة');
        }

        if ($task->attempts()->exists()) {
            abort(422, 'Cannot delete a task with recorded student attempts.');
        }

        $task->delete();

        return response()->json(['message' => 'تم حذف المهمة بنجاح']);
    }

    private function authorizeLessonCourse(Lesson $lesson): void
    {
        $course = $lesson->section?->course;
        if (! $course) {
            abort(404, 'الكورس المرتبط بالدرس غير موجود');
        }

        Gate::authorize('manageAsInstructor', $course);
    }
}
