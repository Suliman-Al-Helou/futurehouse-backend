<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\Course;
use App\Models\Section;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SectionController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService,
    ) {}

    /** Sections + lessons of a course; shape `{sections: [...]}` consumed by frontend. */
    public function index(Course $course): JsonResponse
    {
        $course->load('sections.lessons');

        return response()->json(['sections' => SectionResource::collection($course->sections)]);
    }

    /** Adds a section to a course. */
    public function store(SectionRequest $request, Course $course): JsonResponse
    {
        $section = $this->courseService->addSection($course, $request->validated());

        return response()->json(new SectionResource($section), 201);
    }

    /** Deletes a section (lessons cascade). */
    public function destroy(Section $section): Response
    {
        $section->delete();

        return response()->noContent();
    }
}
