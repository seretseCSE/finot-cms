<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddGuardianAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGuardianRequest;
use App\Http\Requests\Api\V1\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Support\PhoneNumber;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class GuardianController extends Controller
{
    /**
     * Cross-school parent lookup for "attach existing guardian" — parents are
     * GLOBAL persons (ADR-011), so a parent registered at another Temari school
     * is reused rather than duplicated. Deliberately data-minimal: name, public
     * id, masked phone and children count only; requires guardians.manage in
     * the active context. Exact public-id or phone matches; name matches need
     * 3+ characters.
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('guardians.manage'), 403);

        $raw = trim((string) $request->query('q', ''));

        if ($raw === '') {
            return response()->json(['data' => []]);
        }

        $publicId = PublicId::normalize($raw);
        $phoneSearch = PhoneNumber::normalize($raw) ?? $raw;
        $nameSearch = mb_strlen($raw) >= 3;

        $parents = ParentProfile::query()
            ->whereHas('user', function ($u) use ($raw, $publicId, $phoneSearch, $nameSearch): void {
                $u->where('public_id', $publicId)
                    ->orWhere('phone', $phoneSearch)
                    ->when($nameSearch, fn ($q) => $q->orWhere('name', 'ilike', "%{$raw}%"));
            })
            ->with(['user:id,name,phone,public_id', 'guardianships' => fn ($q) => $q->latest('id')])
            ->withCount('guardianships')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $parents->map(function (ParentProfile $parent): array {
                // The parent's most recent link seeds the new link's form —
                // relationship and consent flags rarely differ between siblings.
                $latest = $parent->guardianships->first();

                return [
                    'parent_id' => $parent->id,
                    'name' => $parent->user?->name,
                    'public_id' => $parent->user?->public_id,
                    'phone' => self::maskPhone($parent->user?->phone),
                    'children_count' => $parent->guardianships_count,
                    'defaults' => $latest === null ? null : [
                        'relationship' => $latest->relationship->value,
                        'can_view_grades' => $latest->can_view_grades,
                        'can_view_attendance' => $latest->can_view_attendance,
                        'can_pay_fees' => $latest->can_pay_fees,
                        'can_receive_sms' => $latest->can_receive_sms,
                        'emergency_contact' => $latest->emergency_contact,
                    ],
                ];
            })->values(),
        ]);
    }

    private static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 4) {
            return $phone;
        }

        return substr($phone, 0, 4).str_repeat('•', max(strlen($phone) - 6, 0)).substr($phone, -2);
    }

    public function index(Request $request, Student $student): AnonymousResourceCollection|JsonResponse
    {
        // Former schools (no live custody) read the family as it was ON FILE
        // when the student left — the ADR-017 handover snapshot — never the
        // live links, which belong to the receiving school.
        if ($student->isArchiveOnlyFor($request->user(), 'guardians.view')) {
            $snapshot = $student->archiveSnapshotFor($request->user(), 'guardians.view');

            abort_if($snapshot === null, 403);

            return response()->json([
                'data' => $snapshot['guardians'] ?? [],
                'meta' => ['access' => 'archive', 'captured_at' => $snapshot['captured_at'] ?? null],
            ]);
        }

        $this->authorize('viewGuardians', $student);

        $guardians = $student->guardians()
            ->with(['parentProfile.user', 'parentProfile.attachments'])
            ->orderBy('priority_order')
            ->get();

        return GuardianResource::collection($guardians);
    }

    public function store(
        StoreGuardianRequest $request,
        Student $student,
        AddGuardianAction $action,
    ): JsonResponse {
        $this->authorize('manageGuardians', $student);

        $guardian = $action->execute($student, $request->validated());

        return (new GuardianResource($guardian))
            ->additional(['message' => 'Guardian added.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateGuardianRequest $request, StudentGuardian $guardian): GuardianResource
    {
        $this->authorize('update', $guardian);

        $profileFields = ['first_name', 'father_name', 'grandfather_name', 'gender', 'occupation', 'secondary_phone'];
        $userFields = ['phone', 'email'];

        DB::transaction(function () use ($request, $guardian, $profileFields, $userFields): void {
            $guardian->update($request->safe()->except([...$profileFields, ...$userFields]));

            if ($guardian->is_primary) {
                StudentGuardian::where('student_id', $guardian->student_id)
                    ->whereKeyNot($guardian->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            // Profile corrections touch the PERSON (parents are global,
            // ADR-011): the profile row plus the user account's phone/email
            // and display name.
            $profile = $guardian->parentProfile;
            $user = $profile?->user;

            if ($profile !== null && ($profileData = $request->safe()->only($profileFields)) !== []) {
                $profile->update($profileData);

                $displayName = trim(implode(' ', array_filter([
                    $profile->first_name, $profile->father_name, $profile->grandfather_name,
                ])));
                if ($user !== null && $displayName !== '') {
                    $user->update(['name' => $displayName]);
                }
            }

            if ($user !== null && ($userData = $request->safe()->only($userFields)) !== []) {
                $user->update($userData);
            }
        });

        return new GuardianResource($guardian->load('parentProfile.user', 'parentProfile.attachments'));
    }

    public function destroy(StudentGuardian $guardian): JsonResponse
    {
        $this->authorize('delete', $guardian);

        // Every student must keep at least one guardian on file — never let the
        // last link be removed (add the replacement first, then drop this one).
        abort_if(
            StudentGuardian::where('student_id', $guardian->student_id)->count() <= 1,
            422,
            'A student must have at least one guardian.',
        );

        $guardian->delete();

        return response()->json(['message' => 'Guardian removed.']);
    }
}
