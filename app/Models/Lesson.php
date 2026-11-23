<?php

namespace App\Models;

use App\Models\Concerns\ClearsCacheOnChange;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use ClearsCacheOnChange, HasFactory;

    public const WATCH_PERCENT_THRESHOLD = 80;

    public static function flushCache(): void
    {
        ContentCache::forgetCourses();
    }

    protected $fillable = [
        'section_id', 'title', 'description',
        'video_id', 'video_provider', 'duration', 'order', 'is_preview', 'is_published',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_published' => 'boolean',
        'duration' => 'integer',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasOne<Task, $this>
     */
    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    /**
     * @return HasMany<UserLessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function getCourse(): Course
    {
        return $this->section->course;
    }

    public function isPreview(): bool
    {
        return (bool) $this->is_preview;
    }

    public function previousLesson(): ?Lesson
    {
        $ids = $this->getCourse()->orderedLessons()->pluck('lessons.id'); // pluck : give me an id not all the data

        $index = $ids->search($this->id);

        if ($index === false || $index === 0) {
            return null;
        }

        return Lesson::find($ids[$index - 1]);
    }

    public function isCompletedBy(User $user): bool
    {
        return $this->progress()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->exists();
    }

    public function isWatchUnlockedFor(User $user): bool
    {
        $progress = $this->progress()
            ->where('user_id', $user->id)
            ->first();

        return $progress !== null && (float) $progress->watched_percent >= self::WATCH_PERCENT_THRESHOLD;
    }

    public function isUnlockedFor(User $user): bool
    {
        $previous = $this->previousLesson();

        return $previous === null || $previous->isCompletedBy($user);
    }
}
