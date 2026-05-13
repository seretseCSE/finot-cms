<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FileOptimizer
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Optimize a file based on its type.
     *
     * @param string $disk The filesystem disk name
     * @param string $path The file path relative to the disk root
     * @param array $options Optimization options
     * @return string|null The optimized file path (may be same as input if optimized in-place)
     */
    public function optimize(string $disk, string $path, array $options = []): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $mimeType = Storage::disk($disk)->mimeType($path);

        if (str_starts_with($mimeType, 'image/')) {
            return $this->optimizeImage($disk, $path, $options);
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return $this->optimizeAudio($disk, $path, $options);
        }

        if (str_starts_with($mimeType, 'video/')) {
            return $this->optimizeVideo($disk, $path, $options);
        }

        return $path;
    }

    /**
     * Optimize an image: resize if too large, convert to WebP, compress.
     * This is a "visually lossless" approach — quality 85 WebP is
     * indistinguishable from high-quality JPEG for web use.
     */
    public function optimizeImage(string $disk, string $path, array $options = []): ?string
    {
        $maxWidth = $options['max_width'] ?? 1920;
        $maxHeight = $options['max_height'] ?? 1920;
        $quality = $options['quality'] ?? 85;
        $convertToWebp = $options['webp'] ?? true;

        try {
            $fullPath = Storage::disk($disk)->path($path);
            $image = $this->imageManager->read($fullPath);

            // Resize if dimensions exceed max
            $image->scaleDown($maxWidth, $maxHeight);

            // Convert to WebP for smaller file size
            if ($convertToWebp) {
                $newPath = preg_replace('/\.[^.]+$/', '.webp', $path);
                $image->toWebp($quality)->save(Storage::disk($disk)->path($newPath));

                // Delete original if different path
                if ($newPath !== $path) {
                    Storage::disk($disk)->delete($path);
                }

                return $newPath;
            }

            // Otherwise just re-encode at quality
            $image->encodeByExtension(pathinfo($path, PATHINFO_EXTENSION), quality: $quality)
                ->save($fullPath);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Image optimization failed', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
                'context' => 'image_optimization',
                'user_id' => auth()->id(),
            ]);

            // Show notification to users in Filament context
            if (request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Image Optimization Failed')
                    ->body('Unable to optimize image: ' . basename($path) . ' - ' . $e->getMessage())
                    ->warning()
                    ->send();
            }

            return $path;
        }
    }

    /**
     * Optimize audio using ffmpeg if available.
     * Requires ffmpeg to be installed on the server.
     */
    public function optimizeAudio(string $disk, string $path, array $options = []): ?string
    {
        $bitrate = $options['bitrate'] ?? '128k'; // 128kbps is good quality for music

        if (! $this->ffmpegAvailable()) {
            Log::info('Audio optimization skipped: ffmpeg not available', [
                'disk' => $disk,
                'path' => $path,
            ]);

            return $path;
        }

        try {
            $fullPath = Storage::disk($disk)->path($path);
            $tempPath = $fullPath.'.tmp.'.pathinfo($path, PATHINFO_EXTENSION);

            $command = sprintf(
                'ffmpeg -i %s -b:a %s -map_metadata 0 -id3v2_version 3 %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($bitrate),
                escapeshellarg($tempPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempPath) && filesize($tempPath) > 0) {
                rename($tempPath, $fullPath);
            } else {
                @unlink($tempPath);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Audio optimization failed', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
                'context' => 'audio_optimization',
                'user_id' => auth()->id(),
            ]);

            if (request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Audio Optimization Failed')
                    ->body('Unable to optimize audio: ' . basename($path))
                    ->warning()
                    ->send();
            }

            return $path;
        }
    }

    /**
     * Optimize video using ffmpeg if available.
     * Requires ffmpeg to be installed on the server.
     */
    public function optimizeVideo(string $disk, string $path, array $options = []): ?string
    {
        $bitrate = $options['bitrate'] ?? '2000k';
        $preset = $options['preset'] ?? 'fast';

        if (! $this->ffmpegAvailable()) {
            Log::info('Video optimization skipped: ffmpeg not available', [
                'disk' => $disk,
                'path' => $path,
            ]);

            return $path;
        }

        try {
            $fullPath = Storage::disk($disk)->path($path);
            $tempPath = $fullPath.'.tmp.'.pathinfo($path, PATHINFO_EXTENSION);

            $command = sprintf(
                'ffmpeg -i %s -vcodec libx264 -b:v %s -preset %s -acodec aac -b:a 128k -movflags +faststart %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($bitrate),
                escapeshellarg($preset),
                escapeshellarg($tempPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempPath) && filesize($tempPath) > 0) {
                rename($tempPath, $fullPath);
            } else {
                @unlink($tempPath);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Video optimization failed', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
                'context' => 'video_optimization',
                'user_id' => auth()->id(),
            ]);

            if (request()->routeIs('filament.*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Video Optimization Failed')
                    ->body('Unable to optimize video: ' . basename($path))
                    ->warning()
                    ->send();
            }

            return $path;
        }
    }

    /**
     * Check if ffmpeg is available on the system.
     */
    public function ffmpegAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            exec('ffmpeg -version 2>&1', $output, $returnCode);
            $available = $returnCode === 0;
        }

        return $available;
    }
}
