<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FAQ extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'question',
        'question_am',
        'answer',
        'answer_am',
        'display_order',
        'is_featured',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get question snippet for table display
     */
    public function getQuestionSnippetAttribute(): string
    {
        return Str::limit(strip_tags($this->question), 100);
    }

    /**
     * Get answer snippet for table display
     */
    public function getAnswerSnippetAttribute(): string
    {
        return Str::limit(strip_tags($this->answer), 100);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'faqs';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'FAQs';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-question-mark-circle';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
