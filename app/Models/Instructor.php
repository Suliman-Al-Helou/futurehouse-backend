<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    protected $fillable = [
        'name',
        'title',
        'bio',
        'avatar_url',
        'cover_url',
        'specializations',
        'years_experience',
        'rating',
        'total_reviews',
        'students_count',
        'achievements',
        'twitter',
        'linkedin',
        'youtube',
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'rating'           => 'float',
        'total_reviews'    => 'integer',
        'students_count'   => 'integer',
        'achievements'     => 'array',   // JSON ↔ array تلقائياً
    ];

    // العلاقة مع الكورسات عبر instructor_id
    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}