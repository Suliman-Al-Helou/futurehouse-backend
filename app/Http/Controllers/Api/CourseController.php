<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CourseController extends Controller
{

public function index(Request $request): JsonResponse
{
    $key = 'courses:published:' . ($request->level ?? 'all');

    $courses = Cache::remember($key, 300, function () use ($request) {
        return Course::where('status', 'published')
            ->when($request->level, fn ($q) => $q->where('level', $request->level))
            ->withCount(['enrollments as approved_count' => fn ($q) => $q->where('status', 'approved'), 'lessons'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($course) => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'cover_image' => $course->cover_image,
                'level' => $course->level,
                'status' => $course->status,
                'total_duration' => $course->total_duration,
                'what_you_learn' => $course->what_you_learn ?? [],
                'requirements' => $course->requirements ?? [],
                'target_audience' => $course->target_audience ?? [],
                'students_count' => $course->approved_count,
                'rating' => $course->rating ?? 0,
                'reviews' => 0,
                'instructor' => $course->instructor_name ?? '',
                'instructorBio' => '',
                'lessons' => $course->lessons_count ?? 0,
                'tags' => [],
                'price' => $course->price ?? 0,
                'hot' => $course->is_popular ?? false,
                'is_public' => $course->is_public,
            ])->all();
    });

    return response()->json($courses);
}

    public function show(Course $course): JsonResponse
    {
        $course->load(['sections.lessons' => function ($query) {
            $query->select('id', 'section_id', 'title', 'duration', 'order', 'is_preview')
                ->orderBy('order');
        }]);

        abort_if($course->status === 'draft', 404);
        return response()->json([
            ...$course->toArray(),
            'what_you_learn' => $course->what_you_learn ?? [],
            'requirements' => $course->requirements ?? [],
            'target_audience' => $course->target_audience ?? [],
        ]);
    }
}
