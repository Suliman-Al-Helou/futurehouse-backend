<?php

namespace App\Models;

use App\Models\Concerns\ClearsCacheOnChange;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use ClearsCacheOnChange;

    public static function flushCache(): void
    {
        ContentCache::forgetInstructors();
    }

    protected $fillable = [
        'user_id',
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

    /** The user login account associated with this instructor profile. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether this instructor profile is for display/marketing only without a user account. */
    public function isDisplayOnly(): bool
    {
        return is_null($this->user_id);
    }

    // العلاقة مع الكورسات عبر instructor_id
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}
