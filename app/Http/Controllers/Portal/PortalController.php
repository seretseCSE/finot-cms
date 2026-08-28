<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\MarklistItem;
use App\Models\StudentEnrollment;
use App\Models\WithdrawalRequest;
use App\Services\Movement\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalController extends Controller
{
    public function home()
    {
        return view('portal.home');
    }

    public function results()
    {
        $items = MarklistItem::query()
            ->with(['marklist.term', 'marklist.subject', 'marklist.class'])
            ->where('member_id', auth()->user()->member_id)
            ->whereHas('marklist', fn ($q) => $q->where('status', 'approved'))
            ->latest()
            ->get();

        return view('portal.results', compact('items'));
    }

    public function attendance()
    {
        $records = AttendanceRecord::query()
            ->where('member_id', auth()->user()->member_id)
            ->orderByDesc('event_date')
            ->limit(100)
            ->get();

        return view('portal.attendance', compact('records'));
    }

    public function offlineSnapshot()
    {
        $user = auth()->user();

        return response()->json([
            'results' => MarklistItem::query()
                ->with(['marklist.term', 'marklist.subject'])
                ->where('member_id', $user->member_id)
                ->whereHas('marklist', fn ($q) => $q->where('status', 'approved'))
                ->latest()
                ->limit(50)
                ->get(),
            'attendance' => AttendanceRecord::query()
                ->where('member_id', $user->member_id)
                ->orderByDesc('event_date')
                ->limit(50)
                ->get(),
        ]);
    }

    public function withdrawalForm()
    {
        $enrollment = StudentEnrollment::query()
            ->active()
            ->where('member_id', auth()->user()->member_id)
            ->latest()
            ->first();

        $existing = WithdrawalRequest::query()
            ->where('member_id', auth()->user()->member_id)
            ->latest()
            ->first();

        return view('portal.withdrawal', compact('enrollment', 'existing'));
    }

    public function applyWithdrawal(Request $request, WithdrawalService $service)
    {
        $data = $request->validate([
            'reason' => 'required|string|min:10|max:2000',
            'destination' => 'nullable|string|max:255',
        ]);

        $enrollment = StudentEnrollment::query()
            ->active()
            ->where('member_id', auth()->user()->member_id)
            ->latest()
            ->firstOrFail();

        $service->apply(auth()->user(), $enrollment, $data['reason'], $data['destination'] ?? null);

        return redirect()->route('portal.withdrawal')->with('success', 'Withdrawal request submitted.');
    }

    public function printWithdrawal(WithdrawalRequest $withdrawal)
    {
        abort_unless((int) $withdrawal->member_id === (int) auth()->user()->member_id, 403);

        $withdrawal->load(['member', 'enrollment', 'class', 'requestedBy']);

        return view('print.withdrawal', compact('withdrawal'));
    }

    public function profile()
    {
        return view('portal.profile');
    }

    public function updateProfile(Request $request)
    {
        abort_unless(auth()->user()->can('profile.update'), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|confirmed|min:8',
        ]);

        $user = auth()->user();
        $payload = ['name' => $data['name']];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $payload['temp_password_changed'] = true;
        }

        $user->update($payload);

        return back()->with('success', 'Profile updated.');
    }
}
