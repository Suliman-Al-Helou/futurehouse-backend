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
        ->withCount(['enrollments', 'lessons'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(fn($course) => [
            'id'              => $course->id,
            'title'           => $course->title,
            'description'     => $course->description,
            'cover_image'     => $course->cover_image,
            'level'           => $course->level,
            'status'          => $course->status,
            'total_duration'  => $course->total_duration,
            'what_you_learn'  => $course->what_you_learn ?? [],
            'requirements'    => $course->requirements ?? '',
            'target_audience' => $course->target_audience ?? '',
            'students_count'  => $course->enrollments_count,
            'rating'          => $course->rating ?? 0,
            'reviews'         => 0,
            'instructor'      => $course->instructor_name ?? '', // ← من الحقل المباشر
            'instructorBio'   => '',
            'lessons'         => $course->lessons_count ?? 0,
            'tags'            => [],
            'price'           => 0,
            'hot'             => $course->is_popular ?? false, // ← is_popular مش hot
        ]);

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