<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Models\PlatformSetting;
use App\Support\NotificationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The platform SMS whitelist (Temari.et staff, `catalogs.manage`). SMS costs
 * real money per message, so which notification events may text is an
 * OPERATOR decision: the catalog ships defaults, this endpoint edits the
 * live list (`notifications.sms_whitelist` platform setting). In-app + email
 * behavior is code-defined and not editable here — only the metered channel
 * gets a knob.
 */
class NotificationEventCatalogController extends CatalogController
{
    public function index(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $whitelist = NotificationCatalog::smsWhitelist();

        $events = collect(NotificationCatalog::EVENTS)
            ->map(fn (array $def, string $event): array => [
                'event' => $event,
                'category' => $def['category'],
                'severity' => $def['severity'],
                'email' => $def['email'],
                'sms_default' => $def['sms'],
                'sms_enabled' => in_array($event, $whitelist, true),
            ])
            ->values();

        return response()->json([
            'data' => [
                'events' => $events,
                'categories' => NotificationCatalog::CATEGORIES,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertCatalogManager($request);

        $data = $request->validate([
            'sms_whitelist' => ['required', 'array'],
            'sms_whitelist.*' => ['string', Rule::in(array_keys(NotificationCatalog::EVENTS))],
        ]);

        PlatformSetting::set(
            NotificationCatalog::SMS_WHITELIST_KEY,
            array_values(array_unique($data['sms_whitelist'])),
        );
        NotificationCatalog::flushWhitelistCache();

        return $this->index($request)->setStatusCode(200);
    }
}
