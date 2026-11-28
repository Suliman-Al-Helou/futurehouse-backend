<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\UserLessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    // تسجيل في كورس
    public function enroll(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('enroll', $course);

        $isFree = $course->isFree();
        $status = $isFree ? 'approved' : 'pending';

        $enrollment = Enrollment::withTrashed()->firstOrNew([
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
        ]);

        $enrollment->status = $status;
        $enrollment->enrolled_at = now();

        if ($enrollment->trashed()) {
            $enrollment->restore();
        } else {
            $enrollment->save();
        }

        return response()->json([
            'message' => $isFree
                ? 'تم الانضمام للكورس المجاني بنجاح'
                : 'تم إرسال طلب الاشتراك، بانتظار موافقة الإدارة',
            'status' => $status,
        ], 201);
    }

    // كورسات الطالب
    public function myCourses(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // ضمان ظهور الكورسات المجانية التي بدأ الطالب بمشاهدتها حتى لو لم يُنشأ سجل اشتراك مسبقاً
        $watchedFreeCourseIds = Course::query()
            ->where('is_public', true)
            ->whereHas('sections.lessons.progress', fn ($q) => $q->where('user_id', $userId))
            ->pluck('id');

        foreach ($watchedFreeCourseIds as $freeCourseId) {
            Enrollment::firstOrCreate(
                ['user_id' => $userId, 'course_id' => $freeCourseId],
                ['status' => 'approved', 'enrolled_at' => now()]
            );
        }

        $type = $request->query('type');

        $enrollments = Enrollment::where('user_id', $userId)
            ->when(
                $type === 'free' || $type === 'paid',
                fn ($q) => $q->whereIn('course_id', Course::where('is_public', $type === 'free')->pluck('id'))
            )
            ->with('course')
            ->latest()
            ->get();

        // الدرس التالي لكل كورس: أول درس غير مكتمل بترتيب الأقسام ثم الدروس
        $completed = UserLessonProgress::where('user_id', $userId)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->flip();

        $nextByCourse = Lesson::query()
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->whereIn('sections.course_id', $enrollments->pluck('course_id'))
            ->orderBy('sections.order')
            ->orderBy('lessons.order')
            ->get(['lessons.id', 'sections.course_id'])
            ->groupBy('course_id')
            ->map(fn ($lessons) => $lessons->first(fn ($lesson) => ! isset($completed[$lesson->id]))?->id);

        foreach ($enrollments as $enrollment) {
            $enrollment->course?->setAttribute('next_lesson_id', $nextByCourse->get($enrollment->course_id));
        }

        return response()->json($enrollments);
    }

    // التحقق إذا الطالب مسجل في كورس
    public function checkEnrollment(Request $request, Course $course): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->first();

        return response()->json([
            'enrolled' => $enrollment?->status === 'approved',
            'status' => $enrollment?->status,
            'can_watch' => Gate::allows('watch', $course),
            'can_enroll' => Gate::allows('enroll', $course),
        ]);
    }

    public function unenroll(Request $request, Course $course): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment) {
            $wasApproved = $enrollment->status === 'approved';
            $enrollment->delete();

            if ($wasApproved) {
                Course::where('id', $course->id)
                    ->where('students_count', '>', 0)
                    ->decrement('students_count');
            }
        }

        return response()->json([
            'message' => 'تم سحب التسجيل بنجاح',
        ]);
    }
}
