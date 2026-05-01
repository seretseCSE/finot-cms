<?php

namespace App\Filament\Resources\AttendanceSessionResource\Pages;

use App\Filament\Resources\AttendanceSessionResource;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAttendanceSession extends CreateRecord
{
    protected static string $resource = AttendanceSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()->id;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $activeYear = AcademicYear::query()->where('status', 'Active')->first();

        if (! $activeYear) {
            throw new \RuntimeException('No active academic year');
        }

        $classIds = $data['class_ids'] ?? [];
        $sessionDate = $data['session_date'];

        if (empty($classIds) || ! $sessionDate) {
            throw new \RuntimeException('Please select classes and date');
        }

        $session = AttendanceSession::create([
            'session_date' => $sessionDate,
            'academic_year_id' => $activeYear->id,
            'status' => 'Open',
            'created_by' => $data['created_by'],
        ]);

        $session->classes()->sync($classIds);

        $count = count($classIds);
        Notification::make()
            ->title("Attendance session created")
            ->body("Session for {$sessionDate} with {$count} class(es).")
            ->success()
            ->send();

        return $session;
    }
}
