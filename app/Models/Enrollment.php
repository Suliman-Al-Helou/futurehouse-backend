<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'course_id','enrolled_at', 'completed_at','status'];

    protected $casts = [
        'enrolled_at'  => 'datetime',
        'completed_at' => 'datetime',
        'status'       => 'string',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}