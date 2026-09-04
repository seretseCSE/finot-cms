<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\PlatformSetting;
use App\Support\Ethiopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * RFID terminal registry. Creating (or rotating) a device mints a bearer
 * token whose PLAINTEXT is returned exactly once — only the sha256 hash is
 * stored. Branch-anchored writes follow the school-wide targeting pattern.
 */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        logger()->info('DeviceController@index', ['request' => $request->all()]);

        abort_unless($request->user()->hasContextPermission('devices.view'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);
        $branchFilterId = $this->branchFilterId($request, $branch);

        $today = Ethiopia::today();

        $devices = Device::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->withCount([
                'events as events_today' => fn ($q) => $q->whereDate('scanned_at', $today),
                'events as pending_events' => fn ($q) => $q->where('status', DeviceEvent::STATUS_PENDING),
            ])
            ->with(['branch:id,name', 'school:id,name'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $devices->map(fn (Device $d) => $this->row($d))]);
    }

    public function store(Request $request): JsonResponse
    {
        logger()->info('DeviceController@store', ['request' => $request->all()]);
        $branch = $this->targetBranch($request);

        abort_unless(
            $request->user()->hasPermissionForScope('devices.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:80'],
            'audience' => ['required', Rule::in(Device::AUDIENCES)],
        ]);

        $token = Device::mintToken();

        $device = Device::create([
            'school_id' => $branch->school_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'serial_no' => $data['serial_no'] ?? null,
            'audience' => $data['audience'],
            'token_hash' => Device::hashToken($token),
        ]);

        return response()->json([
            'data' => $this->row($device->load('branch:id,name')),
            // Shown once; only the hash is kept.
            'meta' => ['token' => $token],
            'message' => 'Device registered.',
        ], 201);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        logger()->info('DeviceController@update', ['request' => $request->all(), 'device' => $device]);
        $this->authorizeManage($request, $device);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:80'],
            'audience' => ['sometimes', 'required', Rule::in(Device::AUDIENCES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $device->update($data);

        return response()->json([
            'data' => $this->row($device->refresh()->load('branch:id,name')),
            'message' => 'Device updated.',
        ]);
    }

    /** Invalidate the current credential and mint a fresh one (shown once). */
    public function rotateToken(Request $request, Device $device): JsonResponse
    {
        logger()->info('DeviceController@rotateToken', ['request' => $request->all(), 'device' => $device]);
        $this->authorizeManage($request, $device);

        $token = Device::mintToken();
        $device->update(['token_hash' => Device::hashToken($token)]);

        return response()->json([
            'meta' => ['token' => $token],
            'message' => 'Device token rotated — update the terminal.',
        ]);
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        $this->authorizeManage($request, $device);

        $device->delete();

        return response()->json(['message' => 'Device removed.']);
    }

    private function authorizeManage(Request $request, Device $device): void
    {
        logger()->info('DeviceController@authorizeManage', ['request' => $request->all(), 'device' => $device]);
        abort_unless(
            $request->user()->hasPermissionForScope('devices.manage', $device->school_id, $device->branch_id),
            403,
        );
    }

    // ─── public docs share link ──────────────────────────────────────────
    // The integration guide is shareable OUTSIDE the panel behind one random
    // token in the URL (a firmware contractor doesn't get a panel account).
    // One link exists at a time; rotating mints a new secret and kills the
    // old link instantly; revoking kills sharing altogether. The token is a
    // capability for PUBLIC DOCS ONLY — it unlocks no data and no API.

    private const DOCS_SHARE_KEY = 'devices.docs_share';

    public function docsShare(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('devices.manage'), 403);

        $stored = (array) (PlatformSetting::get(self::DOCS_SHARE_KEY) ?? []);

        return response()->json(['data' => ['token' => $stored['token'] ?? null]]);
    }

    public function rotateDocsShare(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('devices.manage'), 403);

        $token = Str::random(48);

        PlatformSetting::set(self::DOCS_SHARE_KEY, ['token' => $token]);

        return response()->json(['data' => ['token' => $token]]);
    }

    public function revokeDocsShare(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('devices.manage'), 403);

        // value is a NOT NULL jsonb column — "off" is a token-less object.
        PlatformSetting::set(self::DOCS_SHARE_KEY, ['token' => null]);

        return response()->json(['data' => ['token' => null], 'message' => 'Share link disabled.']);
    }

    /** The public gate: right token → 200, anything else → 404. No data leaves here. */
    public function publicDocs(string $token): JsonResponse
    {
        $stored = (array) (PlatformSetting::get(self::DOCS_SHARE_KEY) ?? []);
        $current = $stored['token'] ?? null;

        abort_unless(is_string($current) && $current !== '' && hash_equals($current, $token), 404);

        return response()->json(['data' => ['valid' => true]]);
    }

    /** @return array<string, mixed> */
    private function row(Device $device): array
    {
        return [
            'id' => $device->id,
            'school_id' => $device->school_id,
            'school_name' => $device->relationLoaded('school') ? $device->school?->name : null,
            'branch_id' => $device->branch_id,
            'branch_name' => $device->branch?->name,
            'name' => $device->name,
            'location' => $device->location,
            'serial_no' => $device->serial_no,
            'audience' => $device->audience,
            'is_active' => $device->is_active,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_event_at' => $device->last_event_at?->toIso8601String(),
            'last_roster_at' => $device->last_roster_at?->toIso8601String(),
            'online' => $device->last_seen_at !== null
                && $device->last_seen_at->gt(now()->subMinutes(Device::ONLINE_WINDOW_MINUTES)),
            'events_today' => (int) ($device->events_today ?? 0),
            'pending_events' => (int) ($device->pending_events ?? 0),
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }
}
