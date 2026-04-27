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
            abort(redirect()->back()->with('error', 'No active academic year'));
        }

        $classIds = $data['class_ids'] ?? [];
        $sessionDate = $data['session_date'];

        if (empty($classIds) || ! $sessionDate) {
            abort(redirect()->back()->with('error', 'Please select classes and date'));
        }

        $firstSession = null;
        foreach ($classIds as $classId) {
            $session = AttendanceSession::create([
                'class_id' => $classId,
                'session_date' => $sessionDate,
                'academic_year_id' => $activeYear->id,
                'status' => 'Open',
                'created_by' => $data['created_by'],
            ]);

            if (! $firstSession) {
                $firstSession = $session;
            }
        }

        $count = count($classIds);
        Notification::make()->title("{$count} attendance sessions created")->success()->send();

        return $firstSession;
    }
}
