<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'title_am',
        'content',
        'content_am',
        'display_order',
        'status',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public static function getResourceName(): string
    {
        return 'course_lessons';
    }

    public static function getNavigationLabel(): string
    {
        return 'Course Lessons';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}
