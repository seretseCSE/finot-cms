<?php

use App\Support\DateFormatter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

if (! function_exists('dualDate')) {
    /**
     * Official-document date: both calendars, Ethiopian first —
     * "Hamle 15, 2018 E.C. (July 22, 2026 G.C.)". Every generated PDF prints
     * dates through this regardless of the school's display setting.
     */
    function dualDate(mixed $date, string $locale = 'en'): string
    {
        if (! is_string($date) && ! $date instanceof CarbonInterface) {
            return $date === null ? '—' : (string) $date;
        }

        $formatted = DateFormatter::dual($date, $locale);

        return $formatted !== '' ? $formatted : (string) $date;
    }
}

if (! function_exists('s3Url')) {
    function s3Url(?string $s3Path, ?string $visibility = null, bool $download = false): ?string
    {
        if (! $s3Path || filter_var($s3Path, FILTER_VALIDATE_URL)) {
            return $s3Path;
        }
        try {
            $cacheKey = config('filesystems.cache_prefix').md5($s3Path.($download ? '_download' : ''));

            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $ttl = config('filesystems.cache_ttl');

            if ($visibility === 'public') {
                $url = Storage::disk('r2_public')->url($s3Path);

                if ($download) {
                    // For public files, append download parameter to the URL
                    $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?').'download=1';
                }

                Cache::put($cacheKey, $url, $ttl);

                return $url;
            }

            return Cache::remember($cacheKey, $ttl, function () use ($s3Path, $ttl, $download) {
                $options = $download ? ['ResponseContentDisposition' => 'attachment'] : [];

                return Storage::temporaryUrl($s3Path, now()->addMinutes($ttl), $options);
            });
        } catch (Throwable $th) {
            // Log error but don't crash
            Log::error('S3 URL generation failed: '.$th->getMessage(), [
                'path' => $s3Path,
                'exception' => get_class($th),
                'download' => $download,
            ]);

            return $s3Path;
        }
    }
}
