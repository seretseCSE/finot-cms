<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasAuditLog
{
    /**
     * Boot the trait.
     */
    protected static function bootHasAuditLog(): void
    {
        static::created(function (Model $model) {
            if (in_array('App\Models\Traits\HasAuditLog', class_uses($model))) {
                $model->observe();
            }
        });
    }

    /**
     * Get the audit log data for this model.
     */
    protected function getAuditLogData(string $action, array $context = []): array
    {
        return [
            'model' => static::class,
            'model_id' => $this->id,
            'action' => $action,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeString(),
            'old_values' => $this->getOldValues(),
            'new_values' => $this->getNewValues(),
            'context' => $context,
        ];
    }

    /**
     * Get the old values before changes.
     */
    protected function getOldValues(): array
    {
        return $this->getDirty();
    }

    /**
     * Get the new values after changes.
     */
    protected function getNewValues(): array
    {
        return $this->only($this->getDirty());
    }

    /**
     * Log model creation to audit log.
     */
    protected function logModelCreation(): void
    {
        $this->logToAudit('created', 'Model created');
    }

    /**
     * Log model update to audit log.
     */
    protected function logModelUpdate(): void
    {
        $this->logToAudit('updated', 'Model updated');
    }

    /**
     * Log model deletion to audit log.
     */
    protected function logModelDeletion(): void
    {
        $this->logToAudit('deleted', 'Model deleted');
    }

    /**
     * Log custom event to audit log.
     */
    protected function logToAudit(string $action, string $description = ''): void
    {
        $auditData = $this->getAuditLogData($action, ['description' => $description]);
        
        Log::channel('audit')->warning('Audit Log', $auditData);
    }

    /**
     * Get the model class name for audit logging.
     */
    protected static function getModelClass(): string
    {
        return static::class;
    }
}
