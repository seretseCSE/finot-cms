<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDeviceEvents;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What a terminal talks to (see AuthenticateDevice for the lane). Three verbs:
 * phone home, pull the branch roster (so scans verify OFFLINE at the gate),
 * and flush the scan queue. Ingest is intentionally dumb — accept, dedupe,
 * ack fast over 2G data; a queued job derives attendance afterwards.
 *
 * Roster freshness is VERSION-DRIVEN: every heartbeat carries roster_version
 * (a cached checksum of the roster this device would receive); the terminal
 * pulls the full roster only when the version it stored no longer matches —
 * so a newly issued or lost card propagates within one heartbeat + cache TTL,
 * not a day, while quiet days cost zero roster bytes.
 */
class DeviceGatewayController extends Controller
{
    /** How long a computed roster version may serve heartbeats before recount. */
    private const VERSION_CACHE_MINUTES = 5;

    /** Lets an offline-first terminal sync its clock, confirm registration and check roster freshness. */
    public function heartbeat(Request $request): JsonResponse
    {
        logger()->info('DeviceGatewayController@heartbeat', ['request' => $request->all()]);
        /** @var Device $device */
        $device = $request->attributes->get('device');

        return response()->json([
            'data' => [
                'name' => $device->name,
                'audience' => $device->audience,
                'server_time' => now()->toIso8601String(),
                'roster_version' => $this->rosterVersion($device),
            ],
        ]);
    }

    /**
     * The roster pull: every card this terminal should ACCEPT locally, as two
     * flat UID arrays (no repeated keys — the payload is a third the size of
     * per-row objects). Scoped to the device's own branch and audience; a card
     * is listed only while its holder is still the school's person (live
     * enrollment for students, active employment for employees), so a lost
     * card or a withdrawn student stops opening the gate at the next sync.
     * Terminals store meta.version and re-pull only when a heartbeat's
     * roster_version differs.
     */
    public function roster(Request $request): JsonResponse
    {
        logger()->info('DeviceGatewayController@roster', ['request' => $request->all()]);
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $groups = $this->rosterGroups($device);
        $version = $this->rosterChecksum($groups);

        // Prime the heartbeat cache so the device that just pulled never gets
        // told "stale" by an older cached version.
        Cache::put($this->versionCacheKey($device), $version, now()->addMinutes(self::VERSION_CACHE_MINUTES));

        $device->forceFill(['last_roster_at' => now()])->saveQuietly();

        return response()->json([
            'data' => $groups,
            'meta' => [
                'students' => count($groups['students']),
                'employees' => count($groups['employees']),
                'version' => $version,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Batched scan upload. Idempotent per (device, event_uid) — the id is
     * synthesized server-side from uid+time, so terminals send only what they
     * saw ({uid, scanned_at}) and re-sending whole batches after an offline
     * stretch is always safe: duplicates are swallowed silently. A firmware
     * that keeps its own scan counter may still send event_uid explicitly.
     */
    public function events(Request $request): JsonResponse
    {
        logger()->info('DeviceGatewayController@events', ['request' => $request->all()]);
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.uid' => ['required', 'string', 'max:32'],
            'events.*.scanned_at' => ['required', 'date'],
            'events.*.event_uid' => ['nullable', 'string', 'max:64'],
        ]);

        $now = now();

        $rows = collect($data['events'])->map(fn (array $event): array => [
            'device_id' => $device->id,
            'school_id' => $device->school_id,
            'branch_id' => $device->branch_id,
            'card_uid' => strtoupper($event['uid']),
            'event_uid' => $event['event_uid']
                ?? hash('sha1', $event['uid'].'|'.$event['scanned_at']),
            // Normalize to UTC explicitly — insertOrIgnore bypasses Eloquent
            // casts, so the raw value must already be in the app timezone.
            'scanned_at' => Carbon::parse($event['scanned_at'])->utc(),
            'received_at' => $now,
            'status' => DeviceEvent::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ])->unique(fn (array $row) => $row['event_uid'])->values();

        // insertOrIgnore + the (device_id, event_uid) unique key = idempotency.
        $accepted = DeviceEvent::insertOrIgnore($rows->all());

        $device->forceFill(['last_event_at' => $now])->saveQuietly();

        if ($accepted > 0) {
            ProcessDeviceEvents::dispatch($device->id);
        }

        return response()->json([
            'data' => [
                'accepted' => $accepted,
                'duplicates' => $rows->count() - $accepted,
            ],
            'message' => 'Events received.',
        ]);
    }

    /**
     * The card UIDs this device should accept, grouped by holder type.
     * Both keys are always present — an employees-only terminal simply gets
     * an empty students array, so firmware never branches on shape.
     *
     * @return array{students: list<string>, employees: list<string>}
     */
    private function rosterGroups(Device $device): array
    {
        logger()->info('DeviceGatewayController@rosterGroups', ['device' => $device]);
        $students = in_array($device->audience, ['students', 'both'], true);
        $employees = in_array($device->audience, ['employees', 'both'], true);

        $cards = IdCard::query()
            ->where('branch_id', $device->branch_id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($device, $students, $employees): void {
                if ($students) {
                    $query->orWhereHasMorph('holder', [Student::class], fn (Builder $s) => $s
                        ->whereHas('enrollments', fn (Builder $e) => $e
                            ->where('branch_id', $device->branch_id)
                            ->whereIn('status', [
                                EnrollmentStatus::Pending->value,
                                EnrollmentStatus::Active->value,
                            ])));
                }

                if ($employees) {
                    $query->orWhereHasMorph('holder', [Employee::class], fn (Builder $e) => $e
                        ->where('is_active', true));
                }
            })
            ->orderBy('card_uid')
            ->get(['card_uid', 'holder_type']);

        return [
            'students' => $cards->filter(fn (IdCard $c) => $c->holder_type !== Employee::class)
                ->pluck('card_uid')->values()->all(),
            'employees' => $cards->filter(fn (IdCard $c) => $c->holder_type === Employee::class)
                ->pluck('card_uid')->values()->all(),
        ];
    }

    /** @param array{students: list<string>, employees: list<string>} $groups */
    private function rosterChecksum(array $groups): string
    {
        return hash('sha256', 's:'.implode(',', $groups['students']).'|e:'.implode(',', $groups['employees']));
    }

    /**
     * The version served on heartbeats. Cached briefly so a fleet of gates
     * heartbeating every few minutes never recounts a 10k-card roster each
     * time — one recount per branch×audience per TTL is the ceiling.
     */
    private function rosterVersion(Device $device): string
    {
        return Cache::remember(
            $this->versionCacheKey($device),
            now()->addMinutes(self::VERSION_CACHE_MINUTES),
            fn (): string => $this->rosterChecksum($this->rosterGroups($device)),
        );
    }

    private function versionCacheKey(Device $device): string
    {
        return "device-roster-version:{$device->branch_id}:{$device->audience}";
    }
}
