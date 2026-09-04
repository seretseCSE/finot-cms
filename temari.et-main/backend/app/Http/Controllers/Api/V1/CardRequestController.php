<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\CardRequest;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\Student;
use App\Services\Notify\Notifier;
use App\Support\Ethiopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The card fulfilment pipeline. Schools open requests via
 * IdCardController@reportLost and follow their status here (read-only);
 * Temari.et staff drive the lifecycle: accept → issue the replacement chip
 * (preparing) → delivering → delivered, or reject.
 */
class CardRequestController extends Controller
{
    use HandlesListQueries;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPlatform = $user->hasPlatformPermission('cards.manage');

        abort_unless($isPlatform || $user->hasContextPermission('cards.report'), 403);

        $branch = $isPlatform ? null : $this->activeBranchOrNull($request);
        $schoolScopeId = $isPlatform ? null : $this->activeSchoolScopeId($request);

        $query = CardRequest::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->with([
                'holder', 'requestedBy:id,name', 'school:id,name', 'branch:id,name',
                'card:id,card_uid', 'newCard:id,card_uid',
            ]);

        if ($statuses = $this->csvValues($request, 'status')) {
            $query->whereIn('status', array_intersect($statuses, CardRequest::STATUSES));
        }

        $this->applySort($query, $request, ['created_at', 'status'], 'created_at');

        $requests = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'data' => collect($requests->items())->map(fn (CardRequest $r) => $this->row($r)),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
                'open_count' => CardRequest::query()
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                    ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
                    ->whereIn('status', CardRequest::OPEN_STATUSES)
                    ->count(),
            ],
        ]);
    }

    /** Platform: move a request along the pipeline (or reject it). */
    public function update(Request $request, CardRequest $cardRequest): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('cards.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(CardRequest::STATUSES)],
        ]);

        if ($data['status'] === 'delivered' && $cardRequest->new_card_id === null) {
            throw ValidationException::withMessages([
                'status' => 'Issue the replacement card before marking the request delivered.',
            ]);
        }

        $cardRequest->update(['status' => $data['status']]);

        $this->notifyStatus($cardRequest);

        return response()->json([
            'data' => $this->row($cardRequest->refresh()->load(['holder', 'requestedBy:id,name', 'school:id,name', 'branch:id,name', 'card:id,card_uid', 'newCard:id,card_uid'])),
            'message' => 'Request updated.',
        ]);
    }

    /**
     * Tell whoever the card belongs to how their request is progressing —
     * a student's family, or an employee's own account.
     */
    private function notifyStatus(CardRequest $cardRequest): void
    {
        $holder = $cardRequest->holder;
        $notifier = app(Notifier::class);
        $data = [
            'name' => $holder?->full_name ?? '',
            'status' => $cardRequest->status,
        ];
        $options = [
            'schoolId' => $cardRequest->school_id,
            'branchId' => $cardRequest->branch_id,
            'dedupeKey' => "card_request:{$cardRequest->id}",
        ];

        if ($holder instanceof Student) {
            $notifier->toFamily($holder, 'family.card_request_decided', $data, $options);
        } elseif ($holder instanceof Employee) {
            $notifier->toUser($holder->user, 'family.card_request_decided', $data, $options);
        }
    }

    /** Platform: scan the freshly printed chip into the request. */
    public function issue(Request $request, CardRequest $cardRequest): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('cards.manage'), 403);

        $data = $request->validate([
            'card_uid' => ['required', 'string', 'max:32'],
        ]);

        abort_if(in_array($cardRequest->status, ['rejected', 'delivered'], true), 422, 'This request is closed.');
        abort_if($cardRequest->new_card_id !== null, 422, 'A replacement was already issued for this request.');

        $uid = strtoupper(trim($data['card_uid']));

        $uidClash = IdCard::query()->where('card_uid', $uid)->where('status', 'active')->exists();
        if ($uidClash) {
            throw ValidationException::withMessages([
                'card_uid' => "Card {$uid} is already active for another person.",
            ]);
        }

        $holderClash = IdCard::query()
            ->where('holder_type', $cardRequest->holder_type)
            ->where('holder_id', $cardRequest->holder_id)
            ->where('status', 'active')
            ->exists();
        abort_if($holderClash, 422, 'This person already has an active card again — reject the request instead.');

        DB::transaction(function () use ($request, $cardRequest, $uid): void {
            $card = IdCard::create([
                'school_id' => $cardRequest->school_id,
                'branch_id' => $cardRequest->branch_id,
                'card_uid' => $uid,
                'holder_type' => $cardRequest->holder_type,
                'holder_id' => $cardRequest->holder_id,
                'issued_on' => Ethiopia::today(),
                'issued_by' => $request->user()->id,
            ]);

            $cardRequest->card?->update(['replaced_by_id' => $card->id]);

            $cardRequest->update([
                'new_card_id' => $card->id,
                // Issuing implies the request is being worked on.
                'status' => in_array($cardRequest->status, ['requested', 'accepted'], true)
                    ? 'preparing'
                    : $cardRequest->status,
            ]);
        });

        return response()->json([
            'data' => $this->row($cardRequest->refresh()->load(['holder', 'requestedBy:id,name', 'school:id,name', 'branch:id,name', 'card:id,card_uid', 'newCard:id,card_uid'])),
            'message' => 'Replacement card issued.',
        ], 201);
    }

    /** @return array<string, mixed> */
    private function row(CardRequest $r): array
    {
        return [
            'id' => $r->id,
            'school_id' => $r->school_id,
            'school_name' => $r->school?->name,
            'branch_id' => $r->branch_id,
            'branch_name' => $r->branch?->name,
            'holder_type' => $r->holder instanceof Employee ? 'employee' : 'student',
            'holder_name' => $r->holder?->full_name,
            'reason' => $r->reason,
            'note' => $r->note,
            'status' => $r->status,
            'lost_card_uid' => $r->card?->card_uid,
            'new_card_uid' => $r->newCard?->card_uid,
            'requested_by_name' => $r->requestedBy?->name,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }
}
