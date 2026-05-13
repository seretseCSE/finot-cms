<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\ObserverNotificationService;

class DocumentObserver
{
    /**
     * Handle the Document "created" event.
     */
    public function created(Document $document): void
    {
        $uploaderName = $document->uploadedBy?->name ?? 'A user';
        $documentTitle = $document->title ?: 'A document';

        ObserverNotificationService::notifyUsersByRoles(
            array_merge(
                ObserverNotificationService::ROLE_GROUPS['finance'],
                ['admin', 'superadmin']
            ),
            'Document Uploaded',
            "{$uploaderName} uploaded document: {$documentTitle}",
            route('filament.admin.resources.documents.edit', $document),
            'document'
        );
    }

    /**
     * Handle the Document "updated" event.
     */
    public function updated(Document $document): void
    {
        if ($document->wasChanged(['title', 'description', 'visibility', 'document_date'])) {
            $uploaderName = $document->uploadedBy?->name ?? 'A user';
            $documentTitle = $document->title ?: 'A document';

            ObserverNotificationService::notifyUsersByRoles(
                array_merge(
                    ObserverNotificationService::ROLE_GROUPS['finance'],
                    ['admin', 'superadmin']
                ),
                'Document Updated',
                "Document '{$documentTitle}' has been updated by {$uploaderName}.",
                route('filament.admin.resources.documents.edit', $document),
                'document'
            );
        }
    }

    /**
     * Handle the Document "deleted" event.
     */
    public function deleted(Document $document): void
    {
        $uploaderName = $document->uploadedBy?->name ?? 'A user';
        $documentTitle = $document->title ?: 'A document';

        ObserverNotificationService::notifyUsersByRoles(
            array_merge(
                ObserverNotificationService::ROLE_GROUPS['finance'],
                ['admin', 'superadmin']
            ),
            'Document Deleted',
            "Document '{$documentTitle}' has been deleted by {$uploaderName}.",
            null,
            'document'
        );
    }

    /**
     * Handle the Document "force deleted" event.
     */
    public function forceDeleted(Document $document): void
    {
        $uploaderName = $document->uploadedBy?->name ?? 'A user';
        $documentTitle = $document->title ?: 'A document';

        ObserverNotificationService::notifyUsersByRoles(
            array_merge(
                ObserverNotificationService::ROLE_GROUPS['finance'],
                ['admin', 'superadmin']
            ),
            'Document Permanently Deleted',
            "Document '{$documentTitle}' has been permanently deleted by {$uploaderName}.",
            null,
            'document'
        );
    }
}
