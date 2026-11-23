<?php

namespace Tests\Support;

/**
 * Records what a probe observed to .trace/probes/<id>.json, stamped so the gate
 * can tell the result came from a real run. Adjust the namespace to match
 * composer.json autoload-dev. Delete after the run (rm -r tests/Probes).
 */
final class ProbeRecorder
{
    public static function save(string $probeId, string $decisionRow, array $observed, string $note = ''): void
    {
        $keyFile = base_path('.trace/.key');
        if (! is_file($keyFile)) {
            throw new \RuntimeException('Probe refused: .trace/.key missing. Run lltt.py init first.');
        }
        if (! preg_match('/^P-\d+$/', $probeId) || ! preg_match('/^D-\d+$/', $decisionRow)) {
            throw new \InvalidArgumentException('Probe id must look like P-01 and row like D-01.');
        }
        if ($observed === []) {
            throw new \InvalidArgumentException('observed must not be empty.');
        }

        $dir = base_path('.trace/probes');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $recordedAt = gmdate('Y-m-d\TH:i:s\Z');
        $stamp = hash_hmac('sha256', $probeId.'|'.$decisionRow.'|'.$recordedAt, trim(file_get_contents($keyFile)));

        file_put_contents(
            $dir.'/'.$probeId.'.json',
            json_encode([
                'probe' => $probeId,
                'decision_row' => $decisionRow,
                'recorded_at' => $recordedAt,
                'stamp' => $stamp,
                'note' => $note,
                'observed' => $observed,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
