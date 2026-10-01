<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomAttendance extends Model
{
    use HasFactory;

    protected $fillable = ['zoom_meeting_id', 'user_id', 'attended_at'];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(ZoomMeeting::class, 'zoom_meeting_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
