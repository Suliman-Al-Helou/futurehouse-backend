<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $section = Section::create([
            'course_id' => 1,
            'title' => 'مقدمة في البرمجة',
            'order' => 1,
        ]);

        Lesson::create([
            'section_id' => $section->id,
            'title' => 'ما هي البرمجة؟',
            'video_id' => 'dQw4w9WgXcQ',
            'duration' => 600,
            'order' => 1,
            'is_preview' => true,
        ]);

        Lesson::create([
            'section_id' => $section->id,
            'title' => 'أول برنامج',
            'video_id' => 'dQw4w9WgXcQ',
            'duration' => 900,
            'order' => 2,
            'is_preview' => false,
        ]);
    }
}
