<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * هل يقدر هاد المستخدم يشاهد محتوى الكورس المحمي (دروس، فيديوهات، تقدّم)؟
     * ⚠️ هاي مش نفسها صفحة "تفاصيل الكورس" العامة (عنوان، وصف، منهج) —
     * هاي مفتوحة للكل أصلًا عبر CourseController@show العام، ومش من شغل
     * هاد الـ policy. الـ policy هون بس لمحتوى الدرس الفعلي (فيديو/مهمة).
     */
    public function watch(User $user, Course $course): bool
    {
        if ($course->is_public) {
            return true; // كورس مجاني — وصول فوري، بدون enrollment إطلاقًا
        }

        return $course->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    /**
     * هل يقدر هاد المستخدم يطلب تسجيل (enroll) بهاد الكورس؟
     * يمنع تسجيل مكرر لو عندو طلب pending أو approved أصلًا، ويمنع
     * طلب enrollment لكورس مجاني أصلًا (ما إله داعي).
     */
    public function enroll(User $user, Course $course): bool
    {
        if ($course->is_public) {
            return false;
        }

        $existing = $course->enrollments()
            ->where('user_id', $user->id)
            ->first();

        return !$existing || $existing->status === 'rejected';
    }
}