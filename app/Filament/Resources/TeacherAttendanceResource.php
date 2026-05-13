<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherAttendanceResource\Pages;
use App\Models\TeacherAttendance;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TeacherAttendanceResource extends Resource
{
    protected static ?string $model = TeacherAttendance::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Teacher Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teacherAssignment.teacher.full_name')
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('teacherAssignment.subject.name')
                    ->label('Subject')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('session.session_date')
                    ->label('Session Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attendance_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Late' => 'warning',
                        'Permission' => 'info',
                    }),

                Tables\Columns\TextColumn::make('session_outcome')
                    ->label('Outcome')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Normal' => 'success',
                        'Cancelled' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('markedBy.name')
                    ->label('Marked By')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('marked_at')
                    ->label('Marked At')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teacher')
                    ->relationship('teacherAssignment.teacher', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('attendance_status')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Late' => 'Late',
                        'Permission' => 'Permission',
                    ]),

                Tables\Filters\SelectFilter::make('session_outcome')
                    ->options([
                        'Normal' => 'Normal',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '>=', $data['from'])))
                            ->when($data['until'], fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '<=', $data['until'])));
                    }),
            ])
            ->defaultSort('marked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAttendances::route('/'),
        ];
    }
}
