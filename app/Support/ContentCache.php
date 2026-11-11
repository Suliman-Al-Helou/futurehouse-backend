<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ContentCache
{
    private const COURSE_KEYS_TRACKER = 'courses:cache_keys';

    public static function trackCourseKey(string $key): void
    {
        $keys = Cache::get(self::COURSE_KEYS_TRACKER, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever(self::COURSE_KEYS_TRACKER, $keys);
        }
    }

    public static function forgetCourses(): void
    {
        foreach (['all', 'beginner', 'intermediate', 'advanced'] as $level) {
            Cache::forget("courses:published:$level");
        }

        $extraKeys = Cache::get(self::COURSE_KEYS_TRACKER, []);
        foreach ($extraKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget(self::COURSE_KEYS_TRACKER);

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
