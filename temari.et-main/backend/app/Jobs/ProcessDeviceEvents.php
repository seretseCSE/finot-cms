<?php

namespace App\Jobs;

use App\Enums\EnrollmentStatus;
use App\Enums\TermStatus;
use App\Models\AttendanceRecord;
use App\Models\Device;
use App\Models\DeviceEvent;
use App\Models\Employee;
use App\Models\EmployeeAttendanceRecord;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\Term;
use App\Support\Ethiopia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Turns raw card taps into attendance rows. Runs per device, oldest scan
 * first, so a terminal that was offline all morning replays cleanly.
 *
 * Derivation rules (same for students and employees):
 *  - first scan of the day creates the mark: present, or late when after the
 *    expected start + grace (employee: their own schedule; student: the
 *    term's class_starts_at) — policy read from the holder's branch;
 *  - a later scan never changes a status, it only extends the day:
 *    check_out = the latest scan after check-in;
 *  - MANUAL WINS: a record a human saved keeps its status and its times —
 *    device scans only fill blanks;
 *  - closed terms reject (the event keeps status closed_term for audit);
 *  - late marks created today feed the guardian-alert job (which itself
 *    applies the branch's notify-late policy).
 */
class ProcessDeviceEvents implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deviceId)
    {
    }

    /** @var array<int, bool> record ids to hand to the notifier */
    private array $notifiable = [];

    public function handle(): void
    {
        $device = Device::find($this->deviceId);

        if ($device === null) {
            return;
        }

        DeviceEvent::query()
            ->where('device_id', $device->id)
            ->where('status', DeviceEvent::STATUS_PENDING)
            ->orderBy('scanned_at')
            ->chunkById(200, function ($events) use ($device): void {
                foreach ($events as $event) {
                    $this->process($device, $event);
                }
            });

        if ($this->notifiable !== []) {
            SendAttendanceNotifications::dispatch(array_keys($this->notifiable));
        }
    }

    private function process(Device $device, DeviceEvent $event): void
    {
        $card = IdCard::query()
            ->where('card_uid', $event->card_uid)
            ->where('school_id', $device->school_id)
            ->orderByRaw("status = 'active' desc")
            ->first();

        if ($card === null) {
            $event->update(['status' => DeviceEvent::STATUS_UNKNOWN_CARD]);

            return;
        }

        $outcome = [
            'id_card_id' => $card->id,
            'holder_type' => $card->holder_type,
            'holder_id' => $card->holder_id,
        ];

        if ($card->status !== 'active') {
            $event->update($outcome + ['status' => DeviceEvent::STATUS_INACTIVE_CARD]);

            return;
        }

        $local = $event->scanned_at->copy()->setTimezone(Ethiopia::TIMEZONE);
        $date = $local->toDateString();
        $time = $local->format('H:i');

        $holder = $card->holder;

        $status = match (true) {
            $holder instanceof Employee => $this->applyEmployeeScan($holder, $date, $time),
            $holder instanceof Student => $this->applyStudentScan($device, $holder, $date, $time),
            default => DeviceEvent::STATUS_UNKNOWN_CARD,
        };

        $event->update($outcome + ['status' => $status]);
    }

    private function applyEmployeeScan(Employee $employee, string $date, string $time): string
    {
        $record = EmployeeAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if ($record === null) {
            $expected = $employee->check_in ? substr((string) $employee->check_in, 0, 5) : null;
            $grace = $employee->branch?->effectiveDeviceLateGrace() ?? 15;

            EmployeeAttendanceRecord::create([
                'school_id' => $employee->school_id,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => $this->arrivalStatus($time, $expected, $grace),
                'source' => 'device',
                'check_in' => $time,
            ]);

            return DeviceEvent::STATUS_PROCESSED;
        }

        $this->extendDay($record, $time);

        return DeviceEvent::STATUS_PROCESSED;
    }

    private function applyStudentScan(Device $device, Student $student, string $date, string $time): string
    {
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null || $enrollment->section_id === null || $enrollment->status !== EnrollmentStatus::Active) {
            return DeviceEvent::STATUS_NO_ENROLLMENT;
        }

        $record = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->where('section_id', $enrollment->section_id)
            ->where('date', $date)
            ->first();

        if ($record !== null) {
            $this->extendDay($record, $time);

            return DeviceEvent::STATUS_PROCESSED;
        }

        $branch = $enrollment->branch;
        $term = $this->termFor($enrollment->branch_id, $date);

        if ($term?->status === TermStatus::Closed) {
            return DeviceEvent::STATUS_CLOSED_TERM;
        }

        $expected = $term?->class_starts_at ? substr((string) $term->class_starts_at, 0, 5) : null;
        $grace = $branch?->effectiveDeviceLateGrace() ?? 15;
        $status = $this->arrivalStatus($time, $expected, $grace);

        $record = AttendanceRecord::create([
            'school_id' => $enrollment->school_id,
            'branch_id' => $enrollment->branch_id,
            'section_id' => $enrollment->section_id,
            'student_id' => $student->id,
            'academic_year_id' => $enrollment->academic_year_id,
            'term_id' => $term?->id,
            'date' => $date,
            'status' => $status,
            'source' => 'device',
            'device_id' => $device->id,
            'check_in' => $time,
        ]);

        if ($status === 'late' && $date === Ethiopia::today()) {
            $this->notifiable[$record->id] = true;
        }

        return DeviceEvent::STATUS_PROCESSED;
    }

    /**
     * Second and later scans of a day: never touch status, fill a blank
     * check-in, pull check-in earlier for out-of-order device replays, and
     * extend check-out to the latest scan.
     */
    private function extendDay(AttendanceRecord|EmployeeAttendanceRecord $record, string $time): void
    {
        $checkIn = $record->check_in ? substr((string) $record->check_in, 0, 5) : null;
        $checkOut = $record->check_out ? substr((string) $record->check_out, 0, 5) : null;

        if ($checkIn === null) {
            $record->check_in = $time;
        } elseif ($record->source === 'device' && $time < $checkIn) {
            $record->check_in = $time;

            if ($checkOut === null || $checkIn > $checkOut) {
                $record->check_out = $checkIn;
            }
        } elseif ($time > $checkIn && ($checkOut === null || $time > $checkOut)) {
            $record->check_out = $time;
        }

        if ($record->isDirty()) {
            $record->save();
        }
    }

    private function arrivalStatus(string $time, ?string $expected, int $grace): string
    {
        if ($expected === null) {
            return 'present';
        }

        $deadline = Carbon::parse("2000-01-01 {$expected}")->addMinutes($grace)->format('H:i');

        return $time > $deadline ? 'late' : 'present';
    }

    private function termFor(int $branchId, string $date): ?Term
    {
        return Term::query()
            ->where('branch_id', $branchId)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->orderByDesc('is_current')
            ->first()
            ?? Term::query()->where('branch_id', $branchId)->where('is_current', true)->first();
    }
}
