<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomMeeting extends Model
{
    use HasFactory;

    // الزر يفتح قبل اللقاء بـ 15 دقيقة
    public const JOIN_OPENS_MINUTES_BEFORE = 15;

    protected $fillable = [
        'course_name', 'title', 'description', 'zoom_link', 'starts_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
    ];

    public function attendances()
    {
        return $this->hasMany(ZoomAttendance::class);
    }

    public function canJoin(): bool
    {
        return now()->gte($this->starts_at->copy()->subMinutes(self::JOIN_OPENS_MINUTES_BEFORE));
    }
}
