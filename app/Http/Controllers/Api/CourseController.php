<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseDetailResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class CourseController extends Controller
{
    /** Public published courses list with optional level filter and caching. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $level = in_array($request->level, ['beginner', 'intermediate', 'advanced'], true)
            ? $request->level
            : null;

        $courses = Cache::remember('courses:published:'.($level ?? 'all'), 300, function () use ($level) {
            return Course::forPublicList($level)->get();
        });

        return CourseResource::collection($courses);
    }

    /** Single course public view with curriculum (sections & lessons). */
    public function show(Course $course): CourseDetailResource
    {
        abort_if($course->status === 'draft', 404);

        return new CourseDetailResource($course->loadPublicDetails());
    }
}
