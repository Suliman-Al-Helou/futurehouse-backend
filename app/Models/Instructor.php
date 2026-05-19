<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Course;
class Instructor extends Model
{
    protected $fillable = [
        'name', 'title', 'bio',
        'avatar_url', 'specializations', 'years_experience',
        'twitter', 'linkedin', 'youtube',
    ];

    protected $casts = [
        'years_experience' => 'integer',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}
