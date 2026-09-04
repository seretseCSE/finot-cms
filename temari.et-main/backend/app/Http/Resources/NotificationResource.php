<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Support\DateFormatter;
use App\Support\NotificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;

/**
 * A feed row, localized AT READ TIME: rows store the event key + params, and
 * title/body render here in the requesting user's preferred_language — switch
 * the app to Amharic and the whole feed re-reads in Amharic.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->preferred_language ?: 'en';
        $params = NotificationCatalog::localizeParams(
            $this->data ?? [],
            $locale,
            DateFormatter::modesFor($this->school_id, $this->branch_id),
        );

        return [
            'id' => $this->id,
            'event' => $this->event,
            'category' => $this->category,
            'title' => Lang::get("notifications.{$this->event}.title", $params, $locale),
            'body' => Lang::get("notifications.{$this->event}.body", $params, $locale),
            'link' => $this->link,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'count' => $this->data['count'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
