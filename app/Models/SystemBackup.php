<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemBackup extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'filename',
        'path',
        'size',
        'status',
        'log_message',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who created this backup.
     */
    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include backups with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include completed backups.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed backups.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get formatted size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if backup can be downloaded.
     */
    public function canBeDownloaded(): bool
    {
        return $this->status === 'completed' && Storage::disk('backups')->exists($this->filename);
    }

    /**
     * Check if backup can be restored.
     */
    public function canBeRestored(): bool
    {
        return $this->status === 'completed' && Storage::disk('backups')->exists($this->filename);
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->canBeDownloaded()) {
            return null;
        }
        
        return route('backup.download', $this->id);
    }

    /**
     * Mark backup as completed.
     */
    public function markAsCompleted(string $logMessage = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'log_message' => $logMessage,
        ]);
    }

    /**
     * Mark backup as failed.
     */
    public function markAsFailed(string $logMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'log_message' => $logMessage,
        ]);
    }

    /**
     * Delete backup file and record.
     */
    public function deleteWithFile(): bool
    {
        // Delete the file
        if (Storage::disk('backups')->exists($this->filename)) {
            Storage::disk('backups')->delete($this->filename);
        }
        
        // Delete the record
        return $this->delete();
    }

    /**
     * Generate unique filename.
     */
    public static function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "backup_{$timestamp}_{$random}.zip";
    }
}
