<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ContentCache
{
    public static function forgetCourses(): void
    {
        foreach (['all', 'beginner', 'intermediate', 'advanced'] as $level) {
            Cache::forget("courses:published:$level");
        }
        FrontendCache::revalidate('courses');
        FrontendCache::revalidate('instructors');   // صفحة المدرب فيها قائمة كورساته

    }

    public static function forgetInstructors(): void
    {
        FrontendCache::revalidate('instructors');
    }

    public static function forgetFaqs(): void
    {
        FrontendCache::revalidate('faqs');
    }
}
