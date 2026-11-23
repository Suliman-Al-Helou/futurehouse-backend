<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine whether the user can manage the course as an instructor or supervising admin.
     */
    public function manageAsInstructor(User $user, Course $course): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasRole('instructor')) {
            return false;
        }

        return $course->instructor_id !== null
            && $course->instructor_id === $user->instructor?->id;
    }

    /**
     * هل يقدر هاد المستخدم يشاهد محتوى الكورس المحمي (دروس، فيديوهات، تقدّم)؟
     * ⚠️ هاي مش نفسها صفحة "تفاصيل الكورس" العامة (عنوان، وصف، منهج) —
     * هاي مفتوحة للكل أصلًا عبر CourseController@show العام، ومش من شغل
     * هاد الـ policy. الـ policy هون بس لمحتوى الدرس الفعلي (فيديو/مهمة).
     */
    public function watch(User $user, Course $course): bool
    {
        if ($course->isFree()) {
            return true; // كورس مجاني — وصول فوري، بدون enrollment إطلاقًا
        }

        return $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    public function watchLesson(User $user, Course $course, Lesson $lesson): bool
    {
        if ($this->manageAsInstructor($user, $course)) {
            return true;
        }

        return $lesson->isPreview()
            || ($this->watch($user, $course) && $lesson->isUnlockedFor($user));
    }

    /**
     * هل يقدر هاد المستخدم يطلب تسجيل (enroll) بهاد الكورس؟
     * يمنع تسجيل مكرر لو عندو طلب pending أو approved أصلًا،
     * ويمنع التسجيل إذا كان الكورس غير منشور (draft أو coming_soon).
     */
    public function enroll(User $user, Course $course): bool
    {
        if ($course->status !== 'published') {
            return false;
        }

        $existing = $course->enrollments()
            ->where('user_id', $user->id)
            ->first();

        return ! $existing || $existing->status === 'rejected';
    }
}
