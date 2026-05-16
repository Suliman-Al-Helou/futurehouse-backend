<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Lesson;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $section = Section::create([
            'course_id' => 1,
            'title'     => 'مقدمة في البرمجة',
            'order'     => 1,
        ]);

        Lesson::create([
            'section_id' => $section->id,
            'title'      => 'ما هي البرمجة؟',
            'video_id'   => 'dQw4w9WgXcQ',
            'duration'   => 600,
            'order'      => 1,
            'is_preview' => true,
        ]);

        Lesson::create([
            'section_id' => $section->id,
            'title'      => 'أول برنامج',
            'video_id'   => 'dQw4w9WgXcQ',
            'duration'   => 900,
            'order'      => 2,
            'is_preview' => false,
        ]);
    }
}