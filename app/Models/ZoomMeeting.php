<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomMeeting extends Model
{
    use HasFactory;

    // الزر يفتح قبل اللقاء بـ 15 دقيقة
    public const JOIN_OPENS_MINUTES_BEFORE = 15;

    // مدة صلاحية كود الحضور بعد إطلاقه
    public const CODE_VALID_MINUTES = 10;

    protected $fillable = [
        'course_id', 'title', 'description', 'zoom_link', 'starts_at', 'duration_minutes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'duration_minutes' => 'integer',
        'attendance_code_expires_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function attendances()
    {
        return $this->hasMany(ZoomAttendance::class);
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes ?? 60);
    }

    public function hasEnded(): bool
    {
        return now()->gt($this->endsAt());
    }

    public function canJoin(): bool
    {
        return now()->gte($this->starts_at->copy()->subMinutes(self::JOIN_OPENS_MINUTES_BEFORE))
            && ! $this->hasEnded();
    }
}
