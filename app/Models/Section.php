<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'order'];

    // الفصل ينتمي لكورس
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // الفصل له كثير من الدروس
    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }
    public function show(Course $course): JsonResponse
{
    $course->load([
        'sections' => function ($query) {
            $query->orderBy('order');
        },
        'sections.lessons' => function ($query) {
            $query->select('id', 'section_id', 'title', 'duration', 'order', 'is_preview', 'video_id')
                  ->orderBy('order');
        }
    ]);

    return response()->json($course);
}
}