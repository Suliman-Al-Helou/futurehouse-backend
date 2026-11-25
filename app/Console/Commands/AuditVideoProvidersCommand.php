<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lesson;
use Illuminate\Console\Command;

class AuditVideoProvidersCommand extends Command
{
    protected $signature = 'videos:audit-providers';

    protected $description = 'Audit all lessons for Launch Gate HLS readiness (exits 1 if any published paid lesson lacks HLS).';

    public function handle(): int
    {
        $lessons = Lesson::query()->orderBy('id')->get();
        $rows = [];
        $hasFailures = false;

        foreach ($lessons as $lesson) {
            $provider = $lesson->video_provider ?: config('services.video.default', 'youtube');
            $isPublished = (bool) ($lesson->is_published ?? true);
            $isPreview = (bool) $lesson->is_preview;
            $hasVideoId = ! empty($lesson->video_id);

            if (! $isPublished) {
                $status = 'INFO';
            } elseif ($provider === 'hls' && $hasVideoId) {
                $status = 'PASS';
            } elseif ($isPreview && $hasVideoId) {
                $status = 'ALLOWED/WARN';
            } else {
                $status = 'FAIL';
                $hasFailures = true;
            }

            $rows[] = [
                $lesson->id,
                $lesson->title,
                $provider,
                $isPreview ? 'yes' : 'no',
                $isPublished ? 'yes' : 'no',
                $status,
            ];
        }

        $this->table(['ID', 'Title', 'Provider', 'Preview', 'Published', 'Status'], $rows);

        if ($hasFailures) {
            $this->error('Launch Gate Audit FAILED: One or more published paid lessons are not on HLS.');

            return self::FAILURE;
        }

        $this->info('Launch Gate Audit PASSED.');

        return self::SUCCESS;
    }
}
