<?php

namespace App\Models;

use App\Models\Concerns\ClearsCacheOnChange;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use ClearsCacheOnChange;

    public static function flushCache(): void
    {
        ContentCache::forgetInstructors();
    }

    protected $fillable = [
        'name',
        'title',
        'bio',
        'avatar_url',
        'cover_url',
        'specializations',
        'years_experience',
        'rating',
        'total_reviews',
        'students_count',
        'achievements',
        'twitter',
        'linkedin',
        'youtube',
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'rating' => 'float',
        'total_reviews' => 'integer',
        'students_count' => 'integer',
        'achievements' => 'array',   // JSON ↔ array تلقائياً
    ];

    /** Instructor with courses and their approved-student counts. */
    public function scopeWithCourseStats(Builder $query): Builder
    {
        return $query->with(['courses' => fn ($q) => $q->withCount([
            'enrollments as approved_count' => fn ($e) => $e->where('status', 'approved'),
        ])]);
    }

    // العلاقة مع الكورسات عبر instructor_id
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}
