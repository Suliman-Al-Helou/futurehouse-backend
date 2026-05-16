<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id', 'title', 'description',
        'video_id', 'duration', 'order', 'is_preview',
    ];

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
}