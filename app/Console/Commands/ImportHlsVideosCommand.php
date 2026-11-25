<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportHlsVideosCommand extends Command
{
    protected $signature = 'videos:import-hls
        {--map= : Path to a JSON or CSV mapping file (youtube_id => bunny_guid)}
        {--dir= : Path to a local directory of MP4 files named <lesson_id>.mp4 or <youtube_id>.mp4}';

    protected $description = 'Migrate development YouTube lessons to Bunny.net Stream HLS via mapping file or local MP4 upload.';

    public function handle(): int
    {
        $mapPath = $this->option('map');
        $dirPath = $this->option('dir');

        if (! $mapPath && ! $dirPath) {
            $this->error('Provide either --map=<file> or --dir=<directory>.');

            return self::FAILURE;
        }

        if ($mapPath) {
            return $this->importFromMapping((string) $mapPath);
        }

        return $this->importFromDirectory((string) $dirPath);
    }

    private function importFromMapping(string $path): int
    {
        if (! is_file($path)) {
            $this->error("Mapping file not found: {$path}");

            return self::FAILURE;
        }

        $mappings = str_ends_with(strtolower($path), '.json')
            ? $this->parseJsonMapping($path)
            : $this->parseCsvMapping($path);

        if ($mappings === null) {
            $this->error("Invalid mapping file format: {$path}");

            return self::FAILURE;
        }

        $updated = 0;
        foreach ($mappings as $entry) {
            $bunnyGuid = $entry['bunny_guid'];
            if ($bunnyGuid === '' || $bunnyGuid === 'REPLACE_WITH_BUNNY_GUID') {
                continue;
            }

            $query = Lesson::query();
            if ($entry['lesson_id'] !== null) {
                $query->where('id', $entry['lesson_id']);
            } elseif ($entry['youtube_id'] !== '') {
                $query->where('video_id', $entry['youtube_id']);
            } else {
                continue;
            }

            $updated += $query->update([
                'video_provider' => 'hls',
                'video_id' => $bunnyGuid,
            ]);
        }

        $this->info("Updated {$updated} lesson(s) to HLS from mapping.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{lesson_id: int|null, youtube_id: string, bunny_guid: string}>|null
     */
    private function parseJsonMapping(string $path): ?array
    {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return null;
        }

        // Structured list format: [{"lesson_id": 2, "youtube_id": "...", "bunny_guid": "..."}]
        if (array_is_list($decoded)) {
            $entries = [];
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $entries[] = [
                    'lesson_id' => isset($item['lesson_id']) ? (int) $item['lesson_id'] : null,
                    'youtube_id' => trim((string) ($item['youtube_id'] ?? '')),
                    'bunny_guid' => trim((string) ($item['bunny_guid'] ?? '')),
                ];
            }

            return $entries;
        }

        // Flat map format: {"youtube_id": "bunny_guid"}
        $entries = [];
        foreach ($decoded as $youtubeId => $bunnyGuid) {
            $entries[] = [
                'lesson_id' => null,
                'youtube_id' => trim((string) $youtubeId),
                'bunny_guid' => trim((string) $bunnyGuid),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{lesson_id: int|null, youtube_id: string, bunny_guid: string}>|null
     */
    private function parseCsvMapping(string $path): ?array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return null;
        }

        $mappings = [];
        $isHeader = true;
        while (($row = fgetcsv($handle)) !== false) {
            if ($isHeader) {
                $isHeader = false;
                if (($row[0] ?? '') === 'youtube_id') {
                    continue;
                }
            }
            if (count($row) >= 2) {
                $mappings[] = [
                    'lesson_id' => null,
                    'youtube_id' => trim((string) $row[0]),
                    'bunny_guid' => trim((string) $row[1]),
                ];
            }
        }
        fclose($handle);

        return $mappings;
    }

    private function importFromDirectory(string $dir): int
    {
        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return self::FAILURE;
        }

        $libraryId = (string) config('services.video.bunny.library_id');
        $apiKey = (string) config('services.video.bunny.api_key');

        if ($libraryId === '' || $apiKey === '') {
            $this->error('BUNNY_STREAM_LIBRARY_ID and BUNNY_STREAM_API_KEY must be configured.');

            return self::FAILURE;
        }

        $files = glob(rtrim($dir, '/').'/*.mp4') ?: [];
        $uploaded = 0;

        foreach ($files as $filePath) {
            $stem = pathinfo($filePath, PATHINFO_FILENAME);
            $lesson = is_numeric($stem)
                ? Lesson::query()->find((int) $stem)
                : Lesson::query()->where('video_id', $stem)->first();

            if (! $lesson) {
                $this->warn("No matching lesson found for {$filePath}; skipping.");

                continue;
            }

            $createRes = Http::withHeaders([
                'AccessKey' => $apiKey,
                'Accept' => 'application/json',
            ])->post("https://video.bunnycdn.com/library/{$libraryId}/videos", [
                'title' => $lesson->title,
            ]);

            if (! $createRes->successful() || empty($createRes->json('guid'))) {
                $this->error("Failed creating Bunny video for lesson {$lesson->id}");

                return self::FAILURE;
            }

            $guid = (string) $createRes->json('guid');
            $uploadRes = Http::withHeaders([
                'AccessKey' => $apiKey,
            ])->withBody((string) file_get_contents($filePath), 'application/octet-stream')
                ->put("https://video.bunnycdn.com/library/{$libraryId}/videos/{$guid}");

            if (! $uploadRes->successful()) {
                $this->error("Failed uploading MP4 for lesson {$lesson->id}");

                return self::FAILURE;
            }

            $lesson->update([
                'video_provider' => 'hls',
                'video_id' => $guid,
            ]);
            $uploaded++;
        }

        $this->info("Uploaded and migrated {$uploaded} lesson(s) to Bunny Stream HLS.");

        return self::SUCCESS;
    }
}
