<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherAttendanceResource\Pages;
use Filament\Schemas\Schema;
use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Attendance Details')
                    ->schema([
                        Select::make('teacher_id')
                            ->label('Teacher')
                            ->options(Teacher::query()->where('status', 'Active')->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('session_id')
                            ->label('Session')
                            ->options(AttendanceSession::query()->orderBy('session_date', 'desc')->pluck('session_date', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('attendance_status')
                            ->label('Status')
                            ->options([
                                'Present' => 'Present',
                                'Absent' => 'Absent',
                                'Late' => 'Late',
                                'Permission' => 'Permission',
                            ])
                            ->required()
                            ->default('Present'),

                        Select::make('session_outcome')
                            ->label('Session Outcome')
                            ->options([
                                'Normal' => 'Normal',
                                'Cancelled' => 'Cancelled',
                                'Substitute_Assigned' => 'Substitute Assigned',
                            ])
                            ->required()
                            ->default('Normal'),

                        TextInput::make('substitute_teacher_name')
                            ->label('Substitute Teacher')
                            ->maxLength(255)
                            ->nullable()
                            ->visible(fn (callable $get) => $get('session_outcome') === 'Substitute_Assigned'),

                        DateTimePicker::make('marked_at')
                            ->label('Marked At')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('session.session_date')
                    ->label('Session Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('attendance_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'Present',
                        'danger' => 'Absent',
                        'warning' => 'Late',
                        'info' => 'Permission',
                    ]),

                Tables\Columns\BadgeColumn::make('session_outcome')
                    ->label('Outcome')
                    ->colors([
                        'success' => 'Normal',
                        'danger' => 'Cancelled',
                        'warning' => 'Substitute_Assigned',
                    ]),

                Tables\Columns\TextColumn::make('substitute_teacher_name')
                    ->label('Substitute')
                    ->searchable()
                    ->toggleable()
                    ->default('-'),

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
                    ->relationship('teacher', 'full_name')
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
                        'Substitute_Assigned' => 'Substitute Assigned',
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
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('marked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAttendances::route('/'),
            'create' => Pages\CreateTeacherAttendance::route('/create'),
            'edit' => Pages\EditTeacherAttendance::route('/{record}/edit'),
        ];
    }
}
