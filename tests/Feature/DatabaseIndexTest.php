<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_and_hot_query_columns_have_indexes(): void
    {
        // 1. tasks.lesson_id foreign key index
        $taskIndexes = $this->getTableIndexes('tasks');
        $this->assertContains('tasks_lesson_id_index', $taskIndexes, 'Missing index on tasks(lesson_id)');

        // 2. zoom_meetings.course_id foreign key index
        $zoomIndexes = $this->getTableIndexes('zoom_meetings');
        $this->assertContains('zoom_meetings_course_id_index', $zoomIndexes, 'Missing index on zoom_meetings(course_id)');

        // 3. enrollments composite indexes
        $enrollmentIndexes = $this->getTableIndexes('enrollments');
        $this->assertContains('enrollments_user_id_status_index', $enrollmentIndexes, 'Missing composite index on enrollments(user_id, status)');
        $this->assertContains('enrollments_course_id_status_index', $enrollmentIndexes, 'Missing composite index on enrollments(course_id, status)');

        // 4. lessons ordering and video_id indexes
        $lessonIndexes = $this->getTableIndexes('lessons');
        $this->assertContains('lessons_section_id_order_index', $lessonIndexes, 'Missing composite index on lessons(section_id, order)');
        $this->assertContains('lessons_video_id_index', $lessonIndexes, 'Missing index on lessons(video_id)');
    }

    /**
     * @return list<string>
     */
    private function getTableIndexes(string $table): array
    {
        $results = DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ?', [$table]);

        return array_map(static fn ($r): string => (string) $r->indexname, $results);
    }
}
