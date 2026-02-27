<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Models\Tour;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use Closure;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class GenerateAttendanceAction extends Actions\Action
{
    protected function setUp(): void
    {
        $this->label('Generate Attendance')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Generate Attendance for Tour')
            ->modalDescription('This will mark all confirmed passengers as present for the selected tour session. This action cannot be undone.')
            ->modalSubmitActionLabel('Generate Attendance')
            ->modalCancelActionLabel('Cancel');
    }

    protected function handle(Tour $record, array $data): void
    {
        // Get the selected session
        $sessionId = $data['session_id'] ?? null;
        
        if (!$sessionId) {
            Notification::make()
                ->title('No Session Selected')
                ->body('Please select a session to generate attendance for.')
                ->danger()
                ->send();
            return;
        }

        // Get confirmed passengers for this session
        $confirmedPassengers = TourPassenger::where('tour_id', $record->id)
            ->where('status', 'Confirmed')
            ->whereHas('session', function ($query) use ($sessionId) {
                $query->where('attendance_session_id', $sessionId);
            })
            ->get();

        if ($confirmedPassengers->isEmpty()) {
            Notification::make()
                ->title('No Confirmed Passengers')
                ->body('No confirmed passengers found for this session.')
                ->warning()
                ->send();
            return;
        }

        // Create attendance records for all confirmed passengers
        $attendanceCount = 0;
        foreach ($confirmedPassengers as $passenger) {
            TourAttendanceSession::create([
                'tour_id' => $record->id,
                'attendance_session_id' => $sessionId,
                'tour_passenger_id' => $passenger->id,
                'status' => 'Present',
                'marked_at' => now(),
                'marked_by' => auth()->user()->id(),
            ]);

            $attendanceCount++;
        }

        // Update passenger status to Attended
        TourPassenger::whereIn('id', $confirmedPassengers->pluck('id'))
            ->update(['status' => 'Attended']);

        Notification::make()
            ->title('Attendance Generated')
            ->body("Successfully generated attendance for {$attendanceCount} passengers in the selected session.")
            ->success()
            ->send();
    }

    protected function getFormFields(): array
    {
        $tour = $this->getRecord();
        
        // Get sessions for this tour
        $sessions = $tour->attendanceSessions()
            ->with(['confirmedPassengers' => function ($query) {
                $query->where('status', 'Confirmed');
            }])
            ->orderBy('date', 'asc')
            ->get()
            ->mapWithKeys(function ($session) {
                $confirmedCount = $session->confirmedPassengers->count();
                return [
                    'id' => $session->id,
                    'name' => "{$session->date} ({$session->start_time} - {$session->end_time})",
                    'confirmed_count' => $confirmedCount,
                ];
            });

        return [
            \Filament\Forms\Components\Select::make('session_id')
                ->label('Session')
                ->options($sessions)
                ->required()
                ->reactive(),
        ];
    }

    public function form(Closure|array|null $form = null): static
    {
        return parent::form($this->getFormFields());
    }
}

