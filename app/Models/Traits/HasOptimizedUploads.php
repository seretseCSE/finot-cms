<?php

namespace App\Models\Traits;

use App\Services\FileOptimizer;
use Illuminate\Support\Facades\Log;

trait HasOptimizedUploads
{
    /**
     * Boot the trait.
     * Automatically optimize uploaded files after the model is saved.
     */
    public static function bootHasOptimizedUploads(): void
    {
        static::saved(function ($model) {
            $model->optimizeUploads();
        });
    }

    /**
     * Optimize all configured upload fields.
     */
    public function optimizeUploads(): void
    {
        $optimizer = app(FileOptimizer::class);

        foreach ($this->optimizedUploads() as $config) {
            $field = is_array($config) ? $config['field'] : $config;
            $disk = is_array($config) ? ($config['disk'] ?? $this->getUploadDisk($field)) : $this->getUploadDisk($field);
            $options = is_array($config) ? ($config['options'] ?? []) : [];

            // Check if the field was just changed or has a value
            if (! $this->wasChanged($field) && ! $this->getAttribute($field)) {
                continue;
            }

            $path = $this->getAttribute($field);

            if (empty($path)) {
                continue;
            }

            try {
                $optimizedPath = $optimizer->optimize($disk, $path, $options);

                // If path changed (e.g., converted to WebP), update the model
                if ($optimizedPath !== $path) {
                    // Use a direct query to avoid triggering another saved event
                    static::withoutEvents(function () use ($field, $optimizedPath) {
                        $this->updateQuietly([$field => $optimizedPath]);
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('Upload optimization failed in model', [
                    'model' => static::class,
                    'field' => $field,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Define which fields should be optimized.
     * Override this in your model.
     *
     * Return an array of field names or arrays with 'field', 'disk', and 'options' keys.
     *
     * Example:
     * return [
     *     'featured_image',
     *     ['field' => 'photo', 'disk' => 'members', 'options' => ['max_width' => 800]],
     * ];
     */
    abstract public function optimizedUploads(): array;

    /**
     * Guess the disk name for a given field.
     * Override this in your model if needed.
     */
    protected function getUploadDisk(string $field): string
    {
        // Try to infer from common conventions
        if (property_exists($this, 'uploadDisks') && isset($this->uploadDisks[$field])) {
            return $this->uploadDisks[$field];
        }

        return config('filesystems.default', 'public');
    }
}
