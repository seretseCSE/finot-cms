<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadSanitizer
{
    /**
     * Allowed MIME types mapped to safe extensions.
     */
    protected array $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/webm' => 'webm',
    ];

    /**
     * Sanitize an uploaded file by quarantining it, verifying MIME magic bytes,
     * stripping EXIF from images, and moving it to the destination disk.
     *
     * @throws \RuntimeException
     */
    public function sanitize(
        TemporaryUploadedFile $file,
        string $directory,
        string $disk,
        array $allowedMimeTypes = []
    ): string {
        $realPath = $file->getRealPath();

        // Verify MIME type using magic bytes
        $realMime = $this->getRealMimeType($realPath);

        if (! empty($allowedMimeTypes) && ! in_array($realMime, $allowedMimeTypes, true)) {
            throw new \RuntimeException("File MIME type [{$realMime}] is not allowed.");
        }

        // Determine safe extension from magic bytes, fallback to client original
        $safeExtension = $this->mimeMap[$realMime] ?? $file->getClientOriginalExtension();
        $filename = Str::ulid() . '.' . $safeExtension;
        $path = trim($directory . '/' . $filename, '/');

        // Step 1: Store to quarantine disk (outside web root)
        $quarantineDisk = Storage::disk('quarantine');
        $quarantineDir = 'uploads/' . date('Y/m/d');
        $quarantinePath = $quarantineDir . '/' . $filename;
        $quarantineDisk->putFileAs($quarantineDir, $file, $filename);

        // Step 2: Strip EXIF from images (re-encode to remove metadata)
        if (str_starts_with($realMime, 'image/') && $realMime !== 'image/svg+xml') {
            $this->stripExif($quarantineDisk->path($quarantinePath), $realMime);
        }

        // Step 3: Move from quarantine to final destination
        $finalDisk = Storage::disk($disk);
        $finalDisk->writeStream($path, $quarantineDisk->readStream($quarantinePath));

        // Step 4: Clean up quarantine
        $quarantineDisk->delete($quarantinePath);

        return $path;
    }

    /**
     * Get the real MIME type from file contents using magic bytes.
     */
    public function getRealMimeType(string $path): string
    {
        if (! class_exists(\finfo::class)) {
            throw new \RuntimeException('Fileinfo extension is required for MIME verification.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if ($mime === false) {
            throw new \RuntimeException('Unable to determine file MIME type from magic bytes.');
        }

        return $mime;
    }

    /**
     * Create a save callback for Filament's FileUpload component.
     */
    public static function saveCallback(string $directory, string $disk, array $allowedMimeTypes = []): Closure
    {
        return function (TemporaryUploadedFile $file) use ($directory, $disk, $allowedMimeTypes): string {
            return app(self::class)->sanitize($file, $directory, $disk, $allowedMimeTypes);
        };
    }

    /**
     * Strip EXIF and other metadata from an image by re-encoding it with GD.
     */
    protected function stripExif(string $path, string $mimeType): void
    {
        if (! extension_loaded('gd')) {
            \Log::warning('GD extension not available; EXIF data will not be stripped.', ['path' => $path]);
            return;
        }

        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if (! $image) {
            \Log::warning('Unable to load image for EXIF stripping.', ['path' => $path, 'mime' => $mimeType]);
            return;
        }

        // Preserve transparency for PNG/GIF/WebP
        if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, 92),
            'image/png' => imagepng($image, $path, 6),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, 92),
            default => null,
        };

        imagedestroy($image);
    }
}
