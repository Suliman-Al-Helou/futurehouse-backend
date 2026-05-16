<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id', 'questions',
        'pass_percentage', 'max_attempts', 'cooldown_minutes',
    ];

    protected $casts = [
        'questions' => 'array',
    ];

    // المهمة تنتمي لدرس
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    // محاولات الطلاب
    public function attempts()
    {
        return $this->hasMany(TaskAttempt::class);
    }
}