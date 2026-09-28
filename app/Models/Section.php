<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'title', 'order'];

    // الفصل ينتمي لكورس
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // الفصل له كثير من الدروس
    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }
    

}