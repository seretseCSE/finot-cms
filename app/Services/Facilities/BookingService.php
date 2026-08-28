<?php

namespace App\Services\Facilities;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingService
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function request(User $actor, array $data): Booking
    {
        if (! $actor->can('facilities.book') && ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to request a booking.']);
        }

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_at' => 'End time must be after start time.']);
        }

        $this->assertNoOverlap((int) $data['facility_id'], $start, $end);

        $booking = Booking::query()->create([
            'facility_id' => $data['facility_id'],
            'department_id' => $data['department_id'] ?? $actor->department_id,
            'booked_by' => $actor->id,
            'purpose' => $data['purpose'],
            'start_at' => $start,
            'end_at' => $end,
            'status' => BookingStatus::Pending,
            'recurrence_rule' => $data['recurrence_rule'] ?? null,
            'class_id' => $data['class_id'] ?? null,
            'rehearsal_id' => $data['rehearsal_id'] ?? null,
        ]);

        $this->notifier->toUsers(
            User::permission('facilities.manage')->get(),
            'bookings.requested',
            ['purpose' => $booking->purpose, 'facility_id' => $booking->facility_id],
            null,
            'booking-'.$booking->id
        );

        if (($data['recurrence_rule'] ?? null) === 'weekly') {
            $this->createWeeklyOccurrences($booking, (int) ($data['weeks'] ?? 4));
        }

        activity()->causedBy($actor)->performedOn($booking)->log('booking.requested');

        return $booking;
    }

    public function confirm(Booking $booking, User $actor): Booking
    {
        if (! $actor->can('facilities.manage') && ! $actor->hasRole(['admin', 'superadmin'])) {
            throw ValidationException::withMessages(['actor' => 'Admin must confirm bookings.']);
        }

        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending bookings can be confirmed.']);
        }

        $this->assertNoOverlap(
            (int) $booking->facility_id,
            $booking->start_at,
            $booking->end_at,
            $booking->id
        );

        $booking->update(['status' => BookingStatus::Confirmed]);

        activity()->causedBy($actor)->performedOn($booking)->log('booking.confirmed');

        return $booking->fresh();
    }

    public function cancel(Booking $booking, User $actor): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled]);
        activity()->causedBy($actor)->performedOn($booking)->log('booking.cancelled');

        return $booking->fresh();
    }

    protected function createWeeklyOccurrences(Booking $source, int $weeks): void
    {
        for ($i = 1; $i < max(1, $weeks); $i++) {
            $start = $source->start_at->copy()->addWeeks($i);
            $end = $source->end_at->copy()->addWeeks($i);

            try {
                $this->assertNoOverlap((int) $source->facility_id, $start, $end);
            } catch (HttpException) {
                continue;
            }

            Booking::query()->create([
                'facility_id' => $source->facility_id,
                'department_id' => $source->department_id,
                'booked_by' => $source->booked_by,
                'purpose' => $source->purpose,
                'start_at' => $start,
                'end_at' => $end,
                'status' => BookingStatus::Pending,
                'recurrence_rule' => 'weekly',
                'class_id' => $source->class_id,
                'rehearsal_id' => $source->rehearsal_id,
            ]);
        }
    }

    protected function assertNoOverlap(int $facilityId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $query = Booking::query()
            ->where('facility_id', $facilityId)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new HttpException(422, 'This facility is already booked for the selected time.');
        }
    }
}
