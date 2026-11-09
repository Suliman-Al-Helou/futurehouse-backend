<?php

namespace App\Models;

use App\Models\Concerns\ClearsCacheOnChange;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use ClearsCacheOnChange, HasFactory;

    public static function flushCache(): void
    {
        ContentCache::forgetCourses();
    }

    // just add in thees filed
    protected $fillable = [
        'title',
        'description',
        'cover_image',
        'level',
        'status',
        'total_duration',
        'what_you_learn',
        'requirements',
        'target_audience',
        'students_count',
        'rating',
        'is_popular',
        'price',
        'is_public',
        'instructor_id',
    ];

    // for return the response like i wana .. laravel not always return the value like i wana
    protected $casts = [
        'requirements' => 'array',
        'target_audience' => 'array',
        'what_you_learn' => 'array',
        'is_popular' => 'boolean',
        'rating' => 'float',
        'total_duration' => 'integer',
        'price' => 'float',
        'is_public' => 'boolean',

    ];

    /** Counts every admin view needs; reused by scope and loadCount(). */
    public static function adminCounts(): array
    {
        return [
            'enrollments',
            'enrollments as approved_count' => fn ($q) => $q->where('status', 'approved'),
        ];
    }

    /** Admin listing: eager-loads instructor + counts to avoid N+1. */
    public function scopeForAdminList(Builder $query): Builder
    {
        return $query->with('instructor')->withCount(self::adminCounts())->latest();
    }

    /** Public published courses listing with approved enrollments and lessons count. */
    public function scopeForPublicList(Builder $query, ?string $level = null): Builder
    {
        return $query->where('status', 'published')
            ->when($level, fn (Builder $q) => $q->where('level', $level))
            ->with('instructor')
            ->withCount([
                'enrollments as approved_count' => fn (Builder $q) => $q->where('status', 'approved'),
                'lessons',
            ])
            ->latest();
    }

    /** Eager load public curriculum details (sections and previewable lessons). */
    public function loadPublicDetails(): self
    {
        return $this->load([
            'instructor',
            'sections.lessons' => fn ($query) => $query
                ->select('id', 'section_id', 'title', 'duration', 'order', 'is_preview')
                ->orderBy('order'),
        ]);
    }

    /** Query scope version for public details eager loading. */
    public function scopeForPublicDetails(Builder $query): Builder
    {
        return $query->with([
            'instructor',
            'sections.lessons' => fn ($query) => $query
                ->select('id', 'section_id', 'title', 'duration', 'order', 'is_preview')
                ->orderBy('order'),
        ]);
    }

    public function getInstructorNameAttribute(): ?string
    {
        return $this->relationLoaded('instructor') ? $this->instructor?->name : null;
    }

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Section::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }

    public function isFree(): bool
    {
        return $this->is_public;
    }

    public function orderedLessons()
    {
        return $this->lessons()
            ->orderBy('sections.order')
            ->orderBy('lessons.order');
    }
}
