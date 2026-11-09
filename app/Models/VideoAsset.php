<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'zoom_meeting_id',
        'title',
        'description',
        'provider',
        'provider_video_id',
        'duration_seconds',
        'type',
        'created_by',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'zoom_meeting_id' => 'integer',
        'duration_seconds' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function zoomMeeting(): BelongsTo
    {
        return $this->belongsTo(ZoomMeeting::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

