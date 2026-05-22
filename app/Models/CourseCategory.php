<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CourseCategory extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'depth',
        'name',
        'name_am',
        'description',
        'icon',
        'slug',
        'display_order',
        'status',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'depth' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (CourseCategory $cat) {
            if (empty($cat->slug)) {
                $base = Str::slug($cat->name);
                $slug = $base;
                $i = 2;
                while (CourseCategory::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $cat->slug = $slug;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    public function activeChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('status', 'Active')
            ->orderBy('display_order');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id');
    }

    public function activeCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id')->where('status', 'Published');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getBreadcrumbsAttribute(): array
    {
        $crumbs = [];
        $current = $this;
        while ($current) {
            $crumbs[] = $current;
            $current = $current->parent;
        }
        return array_reverse($crumbs);
    }

    public function getDescendantCourseIds(): array
    {
        $ids = $this->activeCourses()->pluck('id')->toArray();
        foreach ($this->activeChildren as $child) {
            $ids = array_merge($ids, $child->getDescendantCourseIds());
        }
        return $ids;
    }

    public static function getResourceName(): string
    {
        return 'course_categories';
    }

    public static function getNavigationLabel(): string
    {
        return 'Course Categories';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}
