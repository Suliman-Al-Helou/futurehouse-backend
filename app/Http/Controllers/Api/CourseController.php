<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::where('status', 'published')
            ->when($request->level, fn($q) => $q->where('level', $request->level))
            ->withCount('enrollments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($courses);
    }

public function show(Course $course): JsonResponse
{
    $course->load(['sections' => function ($query) {
        $query->orderBy('order');
    }, 'sections.lessons' => function ($query) {
        $query->orderBy('order');
    }]);

    return response()->json($course);
}
}