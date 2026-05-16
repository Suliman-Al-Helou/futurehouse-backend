<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'task_id', 'answers', 'score', 'passed', 'attempted_at',
    ];

    protected $casts = [
        'answers'      => 'array',
        'passed'       => 'boolean',
        'attempted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}