<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ContentCache
{
    public static function forgetCourses(): void
    {
        foreach (['all', 'beginner', 'intermediate', 'advanced'] as $level) {
            Cache::forget("courses:published:$level");
            // إبطال كاش أحدث 3 كورسات المستخدم في صفحة الهبوط فور تحديث أي كورس
            Cache::forget("courses:published:$level:limit_3");
        }
        FrontendCache::revalidate('courses');
        FrontendCache::revalidate('instructors');   // صفحة المدرب فيها قائمة كورساته
    }

    public static function forgetInstructors(): void
    {
        Cache::forget('instructors:all');
        FrontendCache::revalidate('instructors');
    }

    public static function forgetFaqs(): void
    {
        Cache::forget('faqs:all');
        FrontendCache::revalidate('faqs');
    }
}
