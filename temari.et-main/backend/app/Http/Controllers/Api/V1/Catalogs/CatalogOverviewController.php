<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Models\Bank;
use App\Models\GradeLevel;
use App\Models\HealthCondition;
use App\Models\SchoolDirectoryEntry;
use App\Models\Subject;
use App\Support\NotificationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One round-trip for the catalog hub: row counts + attention flags per
 * catalog, so the landing cards (and the mobile app-style list) can show
 * live numbers without five requests.
 */
class CatalogOverviewController extends CatalogController
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        return response()->json([
            'data' => [
                'subjects' => [
                    'total' => Subject::count(),
                    'platform' => Subject::whereNull('school_id')->count(),
                    'custom' => Subject::whereNotNull('school_id')->count(),
                    'inactive' => Subject::where('is_active', false)->count(),
                ],
                'grade_levels' => [
                    'total' => GradeLevel::count(),
                ],
                'banks' => [
                    'total' => Bank::count(),
                    'wallets' => Bank::where('type', Bank::TYPE_WALLET)->count(),
                    'inactive' => Bank::where('is_active', false)->count(),
                ],
                'health_conditions' => [
                    'total' => HealthCondition::count(),
                    'inactive' => HealthCondition::where('is_active', false)->count(),
                ],
                'school_directory' => [
                    'total' => SchoolDirectoryEntry::count(),
                    'unverified' => SchoolDirectoryEntry::where('is_verified', false)->count(),
                    'on_platform' => SchoolDirectoryEntry::whereNotNull('school_id')->count(),
                ],
                'notification_events' => [
                    'total' => count(NotificationCatalog::EVENTS),
                    'sms_enabled' => count(NotificationCatalog::smsWhitelist()),
                ],
            ],
        ]);
    }
}
