<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRequest;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\LessonResource;
use App\Http\Resources\TaskResource;
use App\Models\Lesson;
use App\Models\Section;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LessonController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    /** Adds a lesson to a section. */
    public function store(LessonRequest $request, Section $section): JsonResponse
    {
        $lesson = $section->lessons()->create($request->validated());

        return response()->json(new LessonResource($lesson), 201);
    }

    /** Updates a lesson. */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $lesson->update($request->validated());

        return response()->json(new LessonResource($lesson));
    }

    /** Deletes a lesson. */
    public function destroy(Lesson $lesson): Response
    {
        $lesson->delete();

        return response()->noContent();
    }

    /** Returns the lesson's task or 404. */
    public function getTask(Lesson $lesson): JsonResponse
    {
        $task = $lesson->task()->first();

        if ($task === null) {
            return response()->json(['message' => 'لا توجد مهمة'], 404);
        }

        return response()->json(new TaskResource($task));
    }

    /** Creates the lesson's task (one per lesson). */
    public function storeTask(TaskRequest $request, Lesson $lesson): JsonResponse
    {
        $task = $this->taskService->store($lesson, $request->validated());

        return response()->json(new TaskResource($task), 201);
    }

    /** Updates the lesson's task. */
    public function updateTask(UpdateTaskRequest $request, Lesson $lesson): JsonResponse
    {
        $task = $lesson->task()->firstOrFail();
        $task->update($request->validated());

        return response()->json(new TaskResource($task));
    }
}
