<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'error_type',
        'error_message',
        'stack_trace',
        'user_id',
        'url',
        'http_method',
        'request_data',
        'user_agent',
    ];

    protected $casts = [
        'request_data' => 'array',
    ];

    protected $dates = [
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted created date in Ethiopian
     */
    public function getEthiopianCreatedAtAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->created_at)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->created_at)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->created_at)['year'];
    }

    /**
     * Get error message snippet
     */
    public function getErrorMessageSnippetAttribute(): string
    {
        return Str::limit($this->error_message, 100);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'error_logs';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Error Logs';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bug-ant';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
}
