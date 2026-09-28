<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    // just add in thees filed
    protected $fillable = [
        'title', 'description', 'cover_image',
        'level', 'status', 'total_duration',
        'what_you_learn', 'requirements', 'target_audience',
        'students_count', 'instructor_name', 'rating', 'is_popular', 'price', 'is_public', 'instructor_id',
    ];

    // for return the response like i wana .. laravel not always return the value like i wana
    protected $casts = [
        'what_you_learn' => 'json',
        'requirements' => 'json',
        'target_audience' => 'json',
        'is_popular' => 'boolean',
        'rating' => 'float',
        'total_duration' => 'integer',
        'price' => 'float',
        'is_public' => 'boolean',

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

    public function isFree(): bool
    {
        return $this->is_public;
    }

    public function orderedLessons()
{
    return $this->lessons()
        ->orderBy('sections.order')
        ->orderBy('lessons.order');
}
}
