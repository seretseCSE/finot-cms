<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FAQ extends BaseModel
{
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'faqs';

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

    /**
     * Boot the model to automatically set created_by.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FAQ $faq) {
            if (auth()->check() && !$faq->created_by) {
                $faq->created_by = auth()->id();
            }
        });
    }

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
