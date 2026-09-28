<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{

    // تسجيل في كورس
    public function enroll(Request $request, Course $course): JsonResponse
    {

        Gate::authorize('enroll', $course);
        Enrollment::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            ['status' => 'pending', 'enrolled_at' => now()]
        );

        return response()->json([
            'message' => 'تم إرسال طلب الاشتراك، بانتظار موافقة الإدارة',
        ], 201);
    }

    // كورسات الطالب
    public function myCourses(Request $request): JsonResponse
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)
            ->with('course')
            ->latest()
            ->get();

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
        Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->delete();

        $course->decrement('students_count');

        return response()->json([
            'message' => 'تم سحب التسجيل بنجاح',
        ]);
    }
}
