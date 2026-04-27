<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Models\Tour;
use App\Models\TourAttendance;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use Filament\Actions;
use Filament\Notifications\Notification;

class GenerateAttendanceAction extends Actions\Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Generate Attendance')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Generate Attendance for Tour')
            ->modalDescription('This will create an attendance session and mark all confirmed passengers as present.')
            ->modalSubmitActionLabel('Generate Attendance')
            ->modalCancelActionLabel('Cancel')
            ->action(function (Tour $record): void {
                $confirmedPassengers = TourPassenger::where('tour_id', $record->id)
                    ->where('status', 'Confirmed')
                    ->get();

                if ($confirmedPassengers->isEmpty()) {
                    Notification::make()
                        ->title('No Confirmed Passengers')
                        ->body('No confirmed passengers found for this tour.')
                        ->warning()
                        ->send();

                    return;
                }

                // Create or reuse attendance session
                $session = TourAttendanceSession::firstOrCreate(
                    [
                        'tour_id' => $record->id,
                        'session_date' => $record->tour_date,
                    ],
                    [
                        'status' => 'Open',
                        'created_by' => auth()->id(),
                    ]
                );

                $attendanceCount = 0;
                foreach ($confirmedPassengers as $passenger) {
                    TourAttendance::updateOrCreate(
                        [
                            'session_id' => $session->id,
                            'passenger_id' => $passenger->id,
                        ],
                        [
                            'status' => 'Present',
                            'marked_at' => now(),
                            'marked_by' => auth()->id(),
                        ]
                    );
                    $attendanceCount++;
                }

                // Update passenger status to Attended
                TourPassenger::whereIn('id', $confirmedPassengers->pluck('id'))
                    ->update(['status' => 'Attended']);

                Notification::make()
                    ->title('Attendance Generated')
                    ->body("Successfully generated attendance for {$attendanceCount} passengers.")
                    ->success()
                    ->send();
            });
    }
}
