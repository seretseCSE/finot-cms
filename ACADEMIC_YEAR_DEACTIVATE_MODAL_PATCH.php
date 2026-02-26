Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AcademicYear $record): bool => $record->status === 'Active')
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate Academic Year')
                    ->modalDescription(function (AcademicYear $record): string {
                        $enrolled = StudentEnrollment::query()->where('academic_year_id', $record->getKey())->count();
                        $sessions = AttendanceSession::query()->where('academic_year_id', $record->getKey())->count();
                        $teacherAttendance = TeacherAttendance::query()
                            ->join('attendance_sessions', 'teacher_attendance.session_id', '=', 'attendance_sessions.id')
                            ->where('attendance_sessions.academic_year_id', $record->getKey())
                            ->count();

                        $summary = "This will deactivate {$record->name}.\\n\\n";
                        $summary .= "Summary stats for {$record->name}:\\n";
                        $summary .= "- Enrolled students: {$enrolled}\\n";
                        $summary .= "- Attendance sessions: {$sessions}\\n";
                        $summary .= "- Teacher attendance records: {$teacherAttendance}\\n";
                        $summary .= "- All enrollments will be marked Completed.\\n\\n";
                        $summary .= "- All attendance sessions will remain accessible but read-only.\\n\\n";
                        $summary .= "Proceed with deactivation?";

                        return $summary;
                    })
                    ->action(function (AcademicYear $record): void {
                        $record->update([
                            'is_active' => false,
                            'status' => 'Deactivated',
                            'deactivated_at' => now(),
                            'deactivated_by' => Auth::id(),
                        ]);

                        StudentEnrollment::query()
                            ->where('academic_year_id', $record->getKey())
                            ->where('status', 'Enrolled')
                            ->update(['status' => 'Completed', 'completion_date' => now()->toDateString()]);

                        AttendanceSession::query()
                            ->where('academic_year_id', $record->getKey())
                            ->where('status', 'Open')
                            ->update(['status' => 'Completed']);

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'academic_year_deactivated',
                            'academic_year_id' => $record->getKey(),
                            'new_value' => [
                                'is_active' => false,
                                'status' => 'Deactivated',
                                'deactivated_at' => now()->toDateTimeString(),
                                'deactivated_by' => Auth::id(),
                            ],
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()->title('Academic year deactivated')->success()->send();
                    }),
