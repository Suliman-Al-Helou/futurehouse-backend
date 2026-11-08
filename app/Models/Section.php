<?php

namespace App\Models;

use App\Models\Concerns\ClearsCacheOnChange;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use ClearsCacheOnChange,  HasFactory;

    public static function flushCache(): void
    {
        ContentCache::forgetCourses();
    }

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
