Tables\Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (AttendanceSession $record): bool => $record->isLocked() && Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']))
                    ->form([
                        Forms\Components\Textarea::make('justification')
                            ->label('Justification')
                            ->required()
                            ->minLength(20)
                            ->rows(3),
                    ])
                    ->action(function (AttendanceSession $record, array $data): void {
                        $record->update([
                            'status' => 'Open',
                            'unlock_justification' => $data['justification'],
                            'unlocked_at' => now(),
                            'unlocked_by' => Auth::id(),
                        ]);

                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'session_unlocked',
                            'entity' => 'attendance_session',
                            'session_id' => $record->getKey(),
                            'new_value' => [
                                'justification' => $data['justification'],
                                'unlocked_by' => Auth::id(),
                                'unlocked_at' => now()->toDateTimeString(),
                            ],
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);

                        Notification::make()->title('Session unlocked')->success()->send();
                    }),
