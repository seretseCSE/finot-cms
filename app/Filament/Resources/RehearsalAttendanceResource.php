<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RehearsalAttendanceResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;

class RehearsalAttendanceResource extends BaseResource
{
    protected static ?string $model = RehearsalAttendance::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationLabel(): string
    {
        return 'Rehearsal Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('rehearsal_attendances.delete');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Attendance Details')
                    ->schema([
                        Select::make('rehearsal_id')
                            ->label('Rehearsal')
                            ->options(Rehearsal::query()->orderBy('date_time', 'desc')->pluck('date_time', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('member_id')
                            ->label('Member')
                            ->options(Member::query()->whereIn('status', ['Active', 'Member'])->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Present' => 'Present',
                                'Absent' => 'Absent',
                                'Excused' => 'Excused',
                                'Late' => 'Late',
                                'Permission' => 'Permission',
                            ])
                            ->required()
                            ->default('Present'),

                        DateTimePicker::make('marked_at')
                            ->label('Marked At')
                            ->required()
                            ->default(now())
                            ->native(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rehearsal.date_time')
                    ->label('Rehearsal')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Present' => 'success',
                        'Absent' => 'danger',
                        'Excused' => 'warning',
                        'Late' => 'info',
                        'Permission' => 'primary',
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
                Tables\Filters\SelectFilter::make('rehearsal')
                    ->relationship('rehearsal', 'date_time')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('member')
                    ->relationship('member', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Present' => 'Present',
                        'Absent' => 'Absent',
                        'Excused' => 'Excused',
                        'Late' => 'Late',
                        'Permission' => 'Permission',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereHas('rehearsal', fn ($sq) => $sq->whereDate('date_time', '>=', $data['from'])))
                            ->when($data['until'], fn ($q) => $q->whereHas('rehearsal', fn ($sq) => $sq->whereDate('date_time', '<=', $data['until'])));
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
            'index' => Pages\ListRehearsalAttendances::route('/'),
            'create' => Pages\CreateRehearsalAttendance::route('/create'),
            'edit' => Pages\EditRehearsalAttendance::route('/{record}/edit'),
        ];
    }
}
