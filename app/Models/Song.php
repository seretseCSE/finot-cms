<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Song extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'song_code',
        'title',
        'lyrics',
        'category_id',
        'subcategory_id',
        'audio_file',
        'video_file',
        'artist',
        'is_active',
        'created_by',
    ];

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
        if (!$this->audio_file) {
            return null;
        }

        return asset('storage/songs-audio/' . $this->audio_file);
    }

    /**
     * Get video URL
     */
    public function getVideoUrlAttribute(): ?string
    {
        if (!$this->video_file) {
            return null;
        }

        return asset('storage/songs-video/' . $this->video_file);
    }

    /**
     * Get has audio badge
     */
    public function getHasAudioAttribute(): bool
    {
        return !empty($this->audio_file);
    }

    /**
     * Get has video badge
     */
    public function getHasVideoAttribute(): bool
    {
        return !empty($this->video_file);
    }

    /**
     * Get formatted lyrics (basic HTML sanitization)
     */
    public function getFormattedLyricsAttribute(): string
    {
        if (!$this->lyrics) {
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
}
