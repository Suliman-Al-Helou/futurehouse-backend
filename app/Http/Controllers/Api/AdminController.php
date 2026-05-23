<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users(): JsonResponse
    {
        $users = User::where('role', '!=', 'admin')
            ->withCount('enrollments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    // ← دالة الطلاب فقط
    public function students(): JsonResponse
    {
        $students = User::where('role', 'student')
            ->withCount('enrollments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($students);
    }

    public function courses(): JsonResponse
    {
        $courses = Course::withCount('enrollments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($courses);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_students' => User::where('role', 'student')->count(),
            'total_courses' => Course::count(),
            'total_enrollments' => Enrollment::count(),
        ]);
    }

    public function enrollments(): JsonResponse
    {
        $enrollments = Enrollment::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($enrollments);
    }

    public function updateEnrollment(Request $request, Enrollment $enrollment): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $enrollment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'تم تحديث حالة التسجيل',
            'enrollment' => $enrollment,
        ]);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'level' => 'required|in:beginner,intermediate,advanced',
            'status' => 'required|in:draft,published,coming_soon',
            'total_duration' => 'nullable|integer',
            'what_you_learn' => 'nullable|array',
            'cover_image' => 'nullable|string',
            'instructor_name' => 'nullable|string',
            'rating' => 'nullable|numeric|min:0|max:5',
            'is_popular' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
        ]);

        $course = Course::create($request->only([
            'title',
            'description',
            'level',
            'status',
            'total_duration',
            'what_you_learn',
            'cover_image',
            'instructor_name',
            'rating',
            'is_popular',
            'price',
        ]));

        return response()->json($course, 201);
    }

    public function updateCourse(Request $request, Course $course)
    {
        $course->update($request->only([
            'title',
            'description',
            'level',
            'status',
            'total_duration',
            'what_you_learn',
            'cover_image',
            'instructor_name',
            'rating',
            'is_popular',
            'price',
        ]));

        return response()->json($course);
    }

    public function deleteCourse(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(['message' => 'تم حذف الكورس']);
    }

    public function storeLesson(Request $request, Section $section): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'video_id' => 'nullable|string',
            'duration' => 'nullable|integer',
            'order' => 'nullable|integer',
            'is_preview' => 'nullable|boolean',
        ]);

        $lesson = $section->lessons()->create($request->only([
            'title',
            'video_id',
            'duration',
            'order',
            'is_preview',
        ]));

        return response()->json($lesson, 201);
    }

    public function updateLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $lesson->update($request->only([
            'title',
            'video_id',
            'duration',
            'order',
            'is_preview',
        ]));

        return response()->json($lesson);
    }

    public function deleteLesson(Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(['message' => 'تم حذف الدرس']);
    }

    public function deleteSection(Section $section): JsonResponse
    {
        $section->delete(); // cascade يحذف الدروس تلقائياً

        return response()->json(['message' => 'تم حذف القسم']);
    }

    // عرض الكورس مع sections و lessons
    public function courseWithSections(Course $course): JsonResponse
    {
        $course->load(['sections.lessons' => function ($query) {
            $query->orderBy('order');
        }]);

        return response()->json($course);
    }

    // إضافة section
    public function storeSection(Request $request, Course $course): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);
        $section = $course->sections()->create([
            'title' => $request->title,
            'order' => $request->order ?? $course->sections()->count(),
        ]);

        return response()->json($section, 201);
    }

    // جلب مهمة درس
    public function getTask(int $lessonId): JsonResponse
    {
        $task = Task::where('lesson_id', $lessonId)->first();
        if (! $task) {
            return response()->json(['message' => 'لا توجد مهمة'], 404);
        }

        return response()->json($task);
    }

    // إنشاء مهمة
    public function storeTask(Request $request, int $lessonId): JsonResponse
    {
        $request->validate([
            'questions' => 'required|array',
            'pass_percentage' => 'required|integer|min:1|max:100',
            'max_attempts' => 'required|integer|min:1',
        ]);

        $task = Task::create([
            'lesson_id' => $lessonId,
            'questions' => $request->questions,
            'pass_percentage' => $request->pass_percentage,
            'max_attempts' => $request->max_attempts,
        ]);

        return response()->json($task, 201);
    }

    // تحديث مهمة
    public function updateTask(Request $request, int $lessonId): JsonResponse
    {
        $task = Task::where('lesson_id', $lessonId)->firstOrFail();

        $task->update([
            'questions' => $request->questions,
            'pass_percentage' => $request->pass_percentage ?? $task->pass_percentage,
            'max_attempts' => $request->max_attempts ?? $task->max_attempts,
        ]);

        return response()->json($task);
    }

    public function getSections(int $id): JsonResponse
    {
        $course = Course::with('sections.lessons')->findOrFail($id);

        return response()->json(['sections' => $course->sections]);
    }

    public function instructors(): JsonResponse
    {
        $instructors = Instructor::withCount('courses')->get()->map(fn($ins) => [
            'id'               => $ins->id,
            'name'             => $ins->name,
            'title'            => $ins->title,
            'bio'              => $ins->bio,
            'avatar_url'       => $ins->avatar_url,
            'cover_url'        => $ins->cover_url,
            'specializations'  => $ins->specializations,
            'years_experience' => $ins->years_experience,
            'rating'           => $ins->rating,
            'total_reviews'    => $ins->total_reviews,
            'students_count'   => $ins->students_count,
            'achievements'     => $ins->achievements ?? [],
            'twitter'          => $ins->twitter,
            'linkedin'         => $ins->linkedin,
            'youtube'          => $ins->youtube,
            'courses_count'    => $ins->courses_count,
        ]);

        return response()->json($instructors);
    }

    // ─── إضافة مدرب ───────────────────────────────────────────────
    public function storeInstructor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'title'            => 'nullable|string|max:255',
            'bio'              => 'nullable|string',
            'avatar_url'       => 'nullable|string',
            'cover_url'        => 'nullable|string',
            'specializations'  => 'nullable|string',
            'years_experience' => 'nullable|integer|min:0',
            'rating'           => 'nullable|numeric|min:0|max:5',
            'total_reviews'    => 'nullable|integer|min:0',
            'students_count'   => 'nullable|integer|min:0',
            'achievements'     => 'nullable|array',
            'achievements.*'   => 'string',
            'twitter'          => 'nullable|string',
            'linkedin'         => 'nullable|string',
            'youtube'          => 'nullable|string',
            'courses'          => 'nullable|array',
            'courses.*'        => 'integer|exists:courses,id',
        ]);

        $instructor = Instructor::create($validated);

        // ربط الكورسات: نحدّث instructor_id في جدول courses
        if (!empty($validated['courses'])) {
            Course::whereIn('id', $validated['courses'])
                ->update(['instructor_id' => $instructor->id]);
        }

        return response()->json($instructor, 201);
    }

    // ─── تعديل مدرب ───────────────────────────────────────────────
    public function updateInstructor(Request $request, Instructor $instructor): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'sometimes|string|max:255',
            'title'            => 'nullable|string|max:255',
            'bio'              => 'nullable|string',
            'avatar_url'       => 'nullable|string',
            'cover_url'        => 'nullable|string',
            'specializations'  => 'nullable|string',
            'years_experience' => 'nullable|integer|min:0',
            'rating'           => 'nullable|numeric|min:0|max:5',
            'total_reviews'    => 'nullable|integer|min:0',
            'students_count'   => 'nullable|integer|min:0',
            'achievements'     => 'nullable|array',
            'achievements.*'   => 'string',
            'twitter'          => 'nullable|string',
            'linkedin'         => 'nullable|string',
            'youtube'          => 'nullable|string',
            'courses'          => 'nullable|array',
            'courses.*'        => 'integer|exists:courses,id',
        ]);

        $instructor->update($validated);

        // تحديث الكورسات المرتبطة:
        // 1. افصل الكورسات القديمة التابعة لهذا المدرب
        Course::where('instructor_id', $instructor->id)->update(['instructor_id' => null]);
        // 2. اربط الكورسات الجديدة
        if (!empty($validated['courses'])) {
            Course::whereIn('id', $validated['courses'])
                ->update(['instructor_id' => $instructor->id]);
        }

        return response()->json($instructor->fresh());
    }


    // ─── حذف مدرب ─────────────────────────────────────────────────
    public function deleteInstructor(Instructor $instructor): JsonResponse
    {
        // فك الربط مع الكورسات قبل الحذف
        Course::where('instructor_id', $instructor->id)->update(['instructor_id' => null]);
        $instructor->delete();

        return response()->json(['message' => 'تم حذف المدرب بنجاح']);
    }

    public function showCourse(Course $course)
    {
        return response()->json($course->load('sections.lessons'));
    }

    public function showInstructor(int $id): JsonResponse
    {
        $ins = Instructor::with([
            'courses' => fn($q) =>
            $q->select(
                'courses.id',
                'title',
                'cover_image',
                'level',
                'total_duration',
                'rating',
                'instructor_id'
            )
                ->withCount('enrollments')
        ])->findOrFail($id);

        return response()->json([
            'id'               => $ins->id,
            'name'             => $ins->name,
            'title'            => $ins->title,
            'bio'              => $ins->bio,
            'avatar_url'       => $ins->avatar_url,
            'cover_url'        => $ins->cover_url,
            'specializations'  => $ins->specializations,
            'years_experience' => $ins->years_experience,
            'rating'           => $ins->rating,
            'total_reviews'    => $ins->total_reviews,
            'students_count'   => $ins->students_count,
            'achievements'     => $ins->achievements ?? [],
            'twitter'          => $ins->twitter,
            'linkedin'         => $ins->linkedin,
            'youtube'          => $ins->youtube,
            'courses'          => $ins->courses->map(fn($c) => [
                'id'             => $c->id,
                'title'          => $c->title,
                'cover_image'    => $c->cover_image,
                'level'          => $c->level,
                'total_duration' => $c->total_duration,
                'rating'         => $c->rating ?? 0,
                'students_count' => $c->enrollments_count ?? 0,
            ]),
        ]);
    }
}
