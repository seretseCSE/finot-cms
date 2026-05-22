<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends BaseModel
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'title_am',
        'description',
        'description_am',
        'category_id',
        'instructor',
        'difficulty',
        'duration',
        'featured_image',
        'icon',
        'status',
        'created_by',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'course_id')->orderBy('display_order');
    }

    public function activeLessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class, 'course_id')
            ->where('status', 'Published')
            ->orderBy('display_order');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getLessonCountAttribute(): int
    {
        return $this->activeLessons()->count();
    }

    public static function getResourceName(): string
    {
        return 'courses';
    }

    public static function getNavigationLabel(): string
    {
        return 'Courses';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}
