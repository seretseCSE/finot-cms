<?php

namespace App\Filament\Pages\Attendance;

use App\Models\Tour;
use App\Models\TourAttendance;
use App\Models\TourAttendanceSession;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class TourAttendancePage extends Page
{
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tour Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    protected string $view = 'filament.pages.attendance.tour-attendance';

    protected static ?string $title = 'Tour Attendance';

    #[Url]
    public ?int $tourId = null;

    public ?int $sessionId = null;

    public bool $isLocked = false;

    public array $passengers = [];

    public array $attendance = [];

    public array $selectedPassengers = [];

    public ?string $tourPlace = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('tours.view');
    }

    public function mount(): void
    {
        if ($this->tourId) {
            $this->loadTourAttendance();
        }
    }

    public function loadTourAttendance(): void
    {
        $tour = Tour::with('confirmedPassengers', 'attendanceSessions.attendanceRecords')->find($this->tourId);

        if (! $tour) {
            Notification::make()
                ->title('Tour not found')
                ->danger()
                ->send();
            return;
        }

        $this->tourPlace = $tour->place;

        $session = $tour->attendanceSessions()->first();

        if (! $session) {
            $this->passengers = [];
            $this->attendance = [];
            $this->sessionId = null;
            $this->isLocked = false;
            return;
        }

        $this->sessionId = $session->id;
        $this->isLocked = $session->status === 'Locked';

        $existing = $session->attendanceRecords->keyBy('passenger_id');

        $this->passengers = [];
        $this->attendance = [];

        foreach ($tour->confirmedPassengers as $passenger) {
            $record = $existing->get($passenger->id);

            $this->passengers[$passenger->id] = [
                'id' => $passenger->id,
                'full_name' => $passenger->full_name,
                'phone' => $passenger->phone,
                'passenger_count' => $passenger->passenger_count,
                'passenger_code' => $passenger->passenger_code,
            ];

            $this->attendance[$passenger->id] = $record?->status ?? 'Not Present';
        }
    }

    public function applyBulkStatus(string $status): void
    {
        if (empty($this->selectedPassengers)) {
            foreach ($this->attendance as $passengerId => $x) {
                $this->attendance[$passengerId] = $status;
            }
        } else {
            foreach ($this->selectedPassengers as $passengerId) {
                if (array_key_exists($passengerId, $this->attendance)) {
                    $this->attendance[$passengerId] = $status;
                }
            }
        }
    }

    public function toggleSelectAll(): void
    {
        $ids = array_keys($this->passengers);

        if (empty($ids)) {
            return;
        }

        if (count(array_intersect($this->selectedPassengers, $ids)) === count($ids)) {
            $this->selectedPassengers = array_values(array_diff($this->selectedPassengers, $ids));
        } else {
            $this->selectedPassengers = array_values(array_unique(array_merge($this->selectedPassengers, $ids)));
        }
    }

    public function saveAttendance(): void
    {
        if (! $this->sessionId) {
            return;
        }

        $session = TourAttendanceSession::find($this->sessionId);

        if (! $session || $session->status === 'Locked') {
            Notification::make()
                ->title('Session is locked')
                ->body('Attendance cannot be modified for a locked session.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($session): void {
            foreach ($this->attendance as $passengerId => $status) {
                $record = TourAttendance::firstOrNew(
                    ['session_id' => $this->sessionId, 'passenger_id' => $passengerId]
                );

                $oldStatus = $record->status;
                $record->status = $status;
                $record->marked_at = now();
                $record->marked_by = Auth::id();
                $record->save();

                if ($oldStatus !== $status) {
                    \Log::channel('audit')->info('Tier 1 Audit Log', [
                        'tier' => 1,
                        'action' => 'tour_attendance_marked',
                        'entity_id' => $record->id,
                        'entity_type' => 'tour_attendance',
                        'old_value' => json_encode(['status' => $oldStatus]),
                        'new_value' => json_encode(['status' => $status]),
                        'user_id' => Auth::id(),
                        'timestamp' => now()->toDateTimeString(),
                    ]);
                }
            }
        });

        Notification::make()
            ->title('Tour attendance saved successfully')
            ->success()
            ->send();
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'Present' => 'green',
            'Not Present' => 'red',
            default => 'gray',
        };
    }

    public function getAttendanceSummaryProperty(): string
    {
        $counts = array_count_values(array_map(fn ($v) => $v ?? '', $this->attendance));
        $parts = [];
        foreach (['Present' => 'green', 'Not Present' => 'red'] as $key => $color) {
            $count = $counts[$key] ?? 0;
            if ($count > 0) {
                $parts[] = "<span class='font-semibold text-{$color}-600'>{$count} {$key}</span>";
            }
        }

        $total = count($this->attendance);
        $marked = $total - ($counts[''] ?? 0);

        return "{$marked}/{$total} marked — " . implode(' | ', $parts);
    }
}
