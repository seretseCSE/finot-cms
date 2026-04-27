<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpDoc extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'key',
        'title',
        'content',
        'category',
        'context_route',
        'display_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the user who created this help doc.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope active help docs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Find help doc by context route.
     */
    public static function findByContextRoute(?string $route): ?self
    {
        if (blank($route)) {
            return null;
        }

        return self::active()
            ->where('context_route', $route)
            ->first();
    }

    /**
     * Get resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'help_docs';
    }

    /**
     * Get navigation label for resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Help Documentation';
    }

    /**
     * Get navigation icon for resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-question-mark-circle';
    }

    /**
     * Get navigation group for resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
}
