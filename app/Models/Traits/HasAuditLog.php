<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * HasAuditLog is applied to every model that extends BaseModel (including
 * BaseModel itself).  To prevent infinite recursion we skip logging when the
 * model being saved is already an AuditLog instance.
 */
trait HasAuditLog
{
    /**
     * Boot the trait.
     */
    protected static function bootHasAuditLog(): void
    {
        static::created(function (Model $model) {
            $model->logModelCreation();
        });

        static::updated(function (Model $model) {
            $model->logModelUpdate();
        });

        static::deleted(function (Model $model) {
            $model->logModelDeletion();
        });
    }

    /**
     * Build the data array used to persist an audit-log row.
     */
    protected function getAuditLogData(string $action, array $context = []): array
    {
        $oldValues = null;
        $newValues = null;

        if ($action === 'created') {
            $newValues = $this->getAttributes();
        } elseif ($action === 'updated') {
            $oldValues = $this->getOriginal();
            $newValues = $this->getChanges();
        } elseif ($action === 'deleted') {
            $oldValues = $this->getAttributes();
        }

        return [
            'tier'           => 'security',
            'user_id'        => Auth::id(),
            'action_type'    => $action,
            'entity_type'    => static::class,
            'entity_id'      => $this->id,
            'old_value'      => $oldValues,
            'new_value'      => $newValues,
            'ip_address'     => request()->ip() ?? '127.0.0.1',
            'user_agent'     => request()->userAgent(),
            'notes'          => $context['description'] ?? '',
            'created_at'     => now(),
        ];
    }

    /**
     * Log model creation.
     */
    protected function logModelCreation(): void
    {
        $this->logToAudit('created', 'Model created');
    }

    /**
     * Log model update.
     */
    protected function logModelUpdate(): void
    {
        $this->logToAudit('updated', 'Model updated');
    }

    /**
     * Log model deletion.
     */
    protected function logModelDeletion(): void
    {
        $this->logToAudit('deleted', 'Model deleted');
    }

    /**
     * Persist the audit record, guarding against recursion on AuditLog itself.
     */
    protected function logToAudit(string $action, string $description = ''): void
    {
        if ($this instanceof AuditLog) {
            return;
        }

        $data = $this->getAuditLogData($action, ['description' => $description]);

        AuditLog::create($data);
    }
}
