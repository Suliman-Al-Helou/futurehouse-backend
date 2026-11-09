<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrontendCache
{
    public static function revalidate(string $tag): void
    {
        $base = config('services.frontend.url');
        $secret = config('services.frontend.revalidate_secret');
        if (! $base || ! $secret) {
            return;
        }

        try {
            Http::timeout(3)->withToken($secret)
                ->post(rtrim($base, '/').'/api/revalidate', ['tag' => $tag])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning("Next revalidate failed ($tag): ".$e->getMessage());
        }
    }
}
