<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseDetailResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CourseController extends Controller
{
    /** Public published courses list with optional level filter and caching. */
    public function index(Request $request): JsonResponse
    {
        $level = in_array($request->level, ['beginner', 'intermediate', 'advanced'], true)
            ? $request->level
            : null;

        $limit = $request->integer('limit');
        $limit = $limit > 0 ? $limit : null;

        $cacheKey = 'courses:published:'.($level ?? 'all').($limit !== null ? ':limit:'.$limit : '');

        ContentCache::trackCourseKey($cacheKey);

        $courses = Cache::remember($cacheKey, 300, function () use ($level, $limit) {
            $query = Course::forPublicList($level);

            if ($limit !== null) {
                $query->limit($limit);
            }

            return CourseResource::collection($query->get())->resolve();
        });

        return response()->json(['data' => $courses]);
    }

    /** Single course public view with curriculum (sections & lessons). */
    public function show(Course $course): CourseDetailResource
    {
        abort_if($course->status === 'draft', 404);

        return new CourseDetailResource($course->loadPublicDetails());
    }
}
