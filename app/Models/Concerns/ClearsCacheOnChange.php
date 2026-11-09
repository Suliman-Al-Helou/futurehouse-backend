<?php

namespace App\Models\Concerns;

trait ClearsCacheOnChange
{
    // كل Model بيستخدم الـ trait لازم يعرّف هالدالة
    abstract public static function flushCache(): void;

    protected static function bootClearsCacheOnChange(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
