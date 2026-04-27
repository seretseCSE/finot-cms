<?php

namespace App\Services;

use App\Models\ExportLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ExportAuditService
{
    /**
     * Log an export action for GDPR/compliance traceability.
     *
     * @param string $resourceType The type of resource being exported (e.g. 'members')
     * @param string $format The export format (e.g. 'xlsx', 'csv', 'pdf')
     * @param int $recordCount Number of rows exported
     * @param array $filters The filters applied to the export
     * @param string|null $filePath Optional file path of the exported file
     */
    public static function log(
        string $resourceType,
        string $format,
        int $recordCount,
        array $filters = [],
        ?string $filePath = null
    ): void {
        ExportLog::create([
            'resource_type' => $resourceType,
            'format' => $format,
            'filters' => $filters,
            'file_path' => $filePath ?? 'exports/' . $resourceType . '.' . $format,
            'record_count' => $recordCount,
            'exported_by' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
