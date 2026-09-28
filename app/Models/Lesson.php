<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id', 'title', 'description',
        'video_id', 'duration', 'order', 'is_preview',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'duration' => 'integer',
        'order' => 'integer',
    ];

    public function watchLesson(User $user, Course $course, Lesson $lesson): bool
    {
        return $lesson->isPreview() || $this->watch($user, $course);
    }

    // الدرس ينتمي لفصل
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    // الدرس له مهمة واحدة
    public function task()
    {
        return $this->hasOne(Task::class);
    }

    // تقدم المستخدمين في هذا الدرس
    public function progress()
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

    public function isUnlockedFor(User $user): bool
{
    $previous = $this->previousLesson();

    return $previous === null || $previous->isCompletedBy($user);
}
}
