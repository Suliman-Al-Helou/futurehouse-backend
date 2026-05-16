<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'cover_image',
        'level', 'status', 'total_duration',
        'what_you_learn', 'requirements', 'target_audience',
        'students_count', 'instructor_name', 'rating', 'is_popular'
    ];

    protected $casts = [
        'what_you_learn' => 'array',
        'is_popular'     => 'boolean',
        'rating'         => 'float',
        'total_duration' => 'integer',
    ];

    public function sections()
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    public function lessons()
    {
        return $this->hasManyThrough(Lesson::class, Section::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}