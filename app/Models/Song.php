<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasOptimizedUploads;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Song extends BaseModel
{
    use HasAuditLog;
    use HasFactory;
    use HasOptimizedUploads;
    use SoftDeletes;

    protected $fillable = [
        'song_code',
        'title',
        'lyrics',
        'category_id',
        'subcategory_id',
        'audio_file',
        'video_url',
        'artist',
        'is_active',
        'created_by',
    ];

    /**
     * Boot the model to automatically set created_by and generate song code.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Song $song) {
            if (auth()->check() && !$song->created_by) {
                $song->created_by = auth()->id();
            }

            // Generate song code if not provided
            if (blank($song->song_code)) {
                $song->song_code = static::generateSongCode();
            }
        });
    }

    /**
     * Generate unique song code in SONG-000001 format
     */
    protected static function generateSongCode(): string
    {
        return \DB::transaction(function () {
            // Lock the table to prevent race conditions (skip for SQLite)
            if (\DB::getDriverName() !== 'sqlite') {
                \DB::statement('SELECT id FROM songs ORDER BY id DESC LIMIT 1 FOR UPDATE');
            }

            $lastId = \DB::table('songs')->max('id') ?? 0;
            $nextId = $lastId + 1;

            return config('finot.song_code_prefix', 'SONG-').str_pad($nextId, 6, '0', STR_PAD_LEFT);
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SongCategory::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(SongSubcategory::class);
    }

    /**
     * Get audio URL
     */
    public function getAudioUrlAttribute(): ?string
    {
        if (! $this->audio_file) {
            return null;
        }

        return asset('storage/songs-audio/'.$this->audio_file);
    }

    /**
     * Get video URL (raw URL stored in database)
     */
    public function getVideoUrlAttribute(): ?string
    {
        return $this->attributes['video_url'] ?? null;
    }

    /**
     * Get embed URL (converts YouTube/Vimeo links to embeddable format)
     */
    public function getEmbedUrlAttribute(): ?string
    {
        $url = $this->attributes['video_url'] ?? null;

        if (! $url) {
            return null;
        }

        // YouTube: https://www.youtube.com/watch?v=ABC -> https://www.youtube.com/embed/ABC
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }

        // YouTube short: https://youtu.be/ABC -> https://www.youtube.com/embed/ABC
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/'.$matches[1];
        }

        // Vimeo: https://vimeo.com/123456 -> https://player.vimeo.com/video/123456
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        // Direct video file or unrecognized — return as-is
        return $url;
    }

    /**
     * Whether the video URL can be embedded as an iframe.
     */
    public function getIsEmbeddableAttribute(): bool
    {
        return $this->embed_url && (
            str_contains($this->embed_url, '/embed/') ||
            str_contains($this->embed_url, 'player.vimeo.com')
        );
    }

    /**
     * Get has audio badge
     */
    public function getHasAudioAttribute(): bool
    {
        return ! empty($this->audio_file);
    }

    /**
     * Get has video badge
     */
    public function getHasVideoAttribute(): bool
    {
        return ! empty($this->video_url);
    }

    /**
     * Get formatted lyrics (basic HTML sanitization)
     */
    public function getFormattedLyricsAttribute(): string
    {
        if (! $this->lyrics) {
            return '';
        }

        // Allow basic HTML tags, strip scripts and inline CSS
        $allowedTags = '<p><br><strong><em><ul><ol><li>';
        $cleanLyrics = strip_tags($this->lyrics, $allowedTags);

        // Remove any remaining script tags or inline styles
        $cleanLyrics = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $cleanLyrics);
        $cleanLyrics = preg_replace('/style=[\'"][^\'"]*[\'"]/', '', $cleanLyrics);

        return $cleanLyrics;
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'songs';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Songs';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-musical-note';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }

    /**
     * Define upload fields to optimize.
     */
    public function optimizedUploads(): array
    {
        return [
            ['field' => 'audio_file', 'disk' => 'songs-audio', 'options' => ['bitrate' => '128k']],
        ];
    }
}
