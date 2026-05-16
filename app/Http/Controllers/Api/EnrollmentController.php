<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    // تسجيل في كورس
    public function enroll(Request $request, Course $course): JsonResponse
    {
        // تحقق إذا مسجل مسبقاً
        $exists = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'أنت مسجل في هذا الكورس مسبقاً',
            ], 409);
        }

        Enrollment::create([
            'user_id'   => $request->user()->id,
            'course_id' => $course->id,
        ]);

        // زيادة عداد الطلاب
        $course->increment('students_count');

        return response()->json([
            'message' => 'تم التسجيل في الكورس بنجاح',
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
        $enrolled = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->exists();

        return response()->json(['enrolled' => $enrolled]);
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